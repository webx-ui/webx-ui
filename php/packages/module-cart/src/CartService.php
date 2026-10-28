<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;
use WebxUi\Cart\Contracts\ChecksOptions;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;

/**
 * Every write to a cart goes through here.
 *
 * A batch is checked whole before anything is written, and then written in one transaction: one
 * bad line and nothing lands, not even the cart. Lines of a bundle point at their parent by its
 * `key` inside the batch; the cart is one level deep. A line like one already in the cart — same
 * type, record and options, and outside any bundle — adds to that line instead of doubling it.
 *
 * A refusal is a {@see ValidationException} keyed by field (`items.0.ref`, `qty`), so the API
 * answers it as Laravel answers a form.
 */
final class CartService
{
    public function __construct(
        private readonly Resolver $resolver,
        private readonly Purchasables $purchasables,
        private readonly CurrencySource $currencies,
        private readonly CartHandle $handle,
        private readonly Repository $config,
        private readonly Translator $translator,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Put a batch of lines into the cart, making the cart when there is none.
     *
     * @param  list<mixed>  $items  each { type, ref, qty?, options?, key?, parent? }
     *
     * @throws ValidationException
     */
    public function add(?Cart $cart, array $items, string $locale): AddResult
    {
        $currency = $cart !== null ? $cart->currency : $this->currencies->currency();

        if ($currency === null) {
            throw ValidationException::withMessages(['currency' => [$this->word('currency', $locale)]]);
        }

        $batchMax = $this->limit('batch_max', 20);

        if ($items === [] || ! array_is_list($items) || count($items) > $batchMax) {
            throw ValidationException::withMessages(['items' => [$this->word('batch', $locale, ['max' => $batchMax])]]);
        }

        [$rows, $errors] = $this->read($items, $locale);

        if ($errors === []) {
            $errors = $this->check($rows, $currency, $locale);
        }

        $existing = $cart !== null ? $cart->items()->get()->all() : [];
        $merges = [];

        if ($errors === []) {
            [$merges, $errors] = $this->merges($rows, $existing, $locale);
        }

        if ($errors === []) {
            $new = count($rows) - count($merges);
            $linesMax = $this->limit('lines_max', 100);

            if (count($existing) + $new > $linesMax) {
                $errors['items'][] = $this->word('lines', $locale, ['max' => $linesMax]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $token = null;

        $added = $this->db->transaction(function () use (&$cart, &$token, $rows, $merges, $locale, $currency): array {
            if ($cart === null) {
                $token = CartCookie::generate();
                $cart = Cart::query()->create([
                    'token' => CartCookie::hash($token),
                    'locale' => $locale,
                    'currency' => $currency,
                ]);
            }

            $ids = [];
            $byKey = [];
            $now = Carbon::now();

            foreach ($rows as $index => $row) {
                if (isset($merges[$index])) {
                    $item = $merges[$index];
                    $item->qty += $row->qty;
                    $item->save();
                    $ids[] = $item->id;

                    continue;
                }

                $line = $row->line ?? throw new LogicException('A checked row has its line.');
                $item = CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'type' => $row->type,
                    'ref_id' => $row->ref,
                    'options' => $row->options === [] ? null : $row->options,
                    'qty' => $row->qty,
                    'parent_id' => $row->parent !== null ? $byKey[$row->parent] : null,
                    'name_at_add' => $this->names($line, $locale),
                    'price_at_add' => $line->price,
                    'added_at' => $now,
                ]);

                if ($row->key !== null) {
                    $byKey[$row->key] = $item->id;
                }

                $ids[] = $item->id;
            }

            $cart->locale = $locale;
            $cart->touch();

            return $ids;
        });

        $this->handle->forget();

        /** @var Cart $cart */
        return new AddResult($cart, array_values(array_unique($added)), $token);
    }

    /**
     * Change a line's qty or options. A line of another cart is as missing as one that never was.
     *
     * @param  array<string, mixed>|null  $options  null — leave them as they are
     *
     * @throws ModelNotFoundException|ValidationException
     */
    public function update(Cart $cart, int $id, ?int $qty, ?array $options, string $locale): CartItem
    {
        $item = $this->item($cart, $id);
        $options = $options !== null ? self::normalise($options) : ($item->options ?? []);
        $ref = new CartRef('line', $item->ref_id, $options);
        $line = $this->resolver->resolve($item->type, [$ref], $locale)['line'] ?? null;
        $errors = [];

        if ($options !== [] && $options !== ($item->options ?? [])) {
            $errors = $this->optionErrors('options', $item->type, $item->ref_id, $options, $locale);
        }

        if ($qty !== null) {
            $parentQty = $item->parent_id !== null ? (int) CartItem::query()->whereKey($item->parent_id)->value('qty') : 1;

            if ($qty < 1 || $qty * max(1, $parentQty) > $this->qtyLimit($line)) {
                $errors['qty'][] = $this->word('qty', $locale, ['max' => $this->qtyLimit($line)]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $item->qty = $qty ?? $item->qty;
        $item->options = $options === [] ? null : $options;

        $this->db->transaction(function () use ($cart, $item, $locale): void {
            $item->save();
            $cart->locale = $locale;
            $cart->touch();
        });

        $this->handle->forget();

        return $item;
    }

    /**
     * Take a line out. The children of a parent stay, each with its qty multiplied by the
     * parent's — they counted per unit of it — and without a parent.
     *
     * @throws ModelNotFoundException
     */
    public function remove(Cart $cart, int $id): void
    {
        $item = $this->item($cart, $id);

        $this->db->transaction(function () use ($cart, $item): void {
            foreach ($item->children()->get() as $child) {
                $child->qty *= $item->qty;
                $child->parent_id = null;
                $child->save();
            }

            $item->delete();
            $cart->touch();
        });

        $this->handle->forget();
    }

    /** The whole cart: its lines and its declined rules go with it. */
    public function clear(Cart $cart): void
    {
        $cart->delete();
        $this->handle->forget();
    }

    /**
     * Options compared as a set: the same choice written in another key order is the same line.
     *
     * @return array<array-key, mixed>
     */
    public static function normalise(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        if (! array_is_list($options)) {
            ksort($options);
        }

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $options[$key] = self::normalise($value);
            }
        }

        return $options;
    }

    /**
     * The batch's shape: types, ids, quantities and the keys a bundle is tied by.
     *
     * @param  list<mixed>  $items
     * @return array{0: array<int, BatchRow>, 1: array<string, list<string>>}
     */
    private function read(array $items, string $locale): array
    {
        $errors = [];
        $rows = [];
        $keys = [];

        foreach ($items as $index => $item) {
            $item = is_array($item) ? $item : [];
            $type = $item['type'] ?? null;
            $ref = filter_var($item['ref'] ?? null, FILTER_VALIDATE_INT);
            $qty = filter_var($item['qty'] ?? 1, FILTER_VALIDATE_INT);
            $key = $item['key'] ?? null;
            $parent = $item['parent'] ?? null;
            $options = $item['options'] ?? null;

            if (! is_string($type) || ! $this->purchasables->has($type)) {
                $errors["items.{$index}.type"][] = $this->word('type', $locale, ['types' => implode(', ', $this->purchasables->types())]);
            }

            if ($ref === false || $ref < 1) {
                $errors["items.{$index}.ref"][] = $this->word('ref', $locale);
            }

            if ($qty === false || $qty < 1) {
                $errors["items.{$index}.qty"][] = $this->word('qty', $locale, ['max' => $this->limit('qty_max', 999)]);
            }

            if ($options !== null && ! is_array($options)) {
                $errors["items.{$index}.options"][] = $this->word('options', $locale);
            }

            if ($key !== null && (! is_scalar($key) || isset($keys[(string) $key]))) {
                $errors["items.{$index}.key"][] = $this->word('key', $locale);
            } elseif ($key !== null) {
                $keys[(string) $key] = $index;
            }

            if ($parent !== null && ! is_scalar($parent)) {
                $errors["items.{$index}.parent"][] = $this->word('parent', $locale);
            }

            $rows[$index] = new BatchRow(
                index: $index,
                type: is_string($type) ? $type : '',
                ref: is_int($ref) ? $ref : 0,
                qty: is_int($qty) ? $qty : 1,
                options: self::normalise($options),
                key: $key !== null && is_scalar($key) ? (string) $key : null,
                parent: $parent !== null && is_scalar($parent) ? (string) $parent : null,
            );
        }

        // A parent is a line of this batch that has no parent of its own: one level deep.
        foreach ($rows as $index => $row) {
            if ($row->parent === null) {
                continue;
            }

            $at = $keys[$row->parent] ?? null;

            if ($at === null || $at === $index || $rows[$at]->parent !== null) {
                $errors["items.{$index}.parent"][] = $this->word('parent', $locale);
            }
        }

        return [$rows, $errors];
    }

    /**
     * What the providers say of the batch: one call a type. Only a line that can be bought, in
     * the cart's currency, within its stock, with options its provider accepts goes in.
     *
     * @param  array<int, BatchRow>  $rows
     * @return array<string, list<string>>
     */
    private function check(array $rows, string $currency, string $locale): array
    {
        $byType = [];

        foreach ($rows as $index => $row) {
            $byType[$row->type][] = new CartRef((string) $index, $row->ref, $row->options);
        }

        $answers = [];

        foreach ($byType as $type => $refs) {
            $answers += $this->resolver->resolve($type, $refs, $locale);
        }

        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $answers[(string) $index] ?? null;

            if ($line === null || ! $line->buyable()) {
                $errors["items.{$index}.ref"][] = $this->word('unavailable', $locale);

                continue;
            }

            if ($line->currency !== $currency) {
                $errors["items.{$index}.ref"][] = $this->word('currency_mismatch', $locale);

                continue;
            }

            $row->line = $line;
            $limit = $this->qtyLimit($line);

            if ($row->qty * $this->parentQty($rows, $row->parent) > $limit) {
                $errors["items.{$index}.qty"][] = $this->word('qty', $locale, ['max' => $limit]);
            }

            if ($row->options !== []) {
                foreach ($this->optionErrors("items.{$index}.options", $row->type, $row->ref, $row->options, $locale) as $field => $messages) {
                    $errors[$field] = [...$errors[$field] ?? [], ...$messages];
                }
            }
        }

        return $errors;
    }

    /**
     * Which rows add to a line already there; the same line twice in one batch folds into the
     * first. Never a line of a bundle, on either side.
     *
     * @param  array<int, BatchRow>  $rows  folded rows are taken out
     * @param  list<CartItem>  $existing
     * @return array{0: array<int, CartItem>, 1: array<string, list<string>>}
     */
    private function merges(array &$rows, array $existing, string $locale): array
    {
        $parents = array_filter(array_map(static fn (BatchRow $row): ?string => $row->parent, $rows));
        $withChildren = array_filter(array_map(static fn (CartItem $item): ?int => $item->parent_id, $existing));
        $targets = [];

        foreach ($existing as $item) {
            if ($item->parent_id === null && ! in_array($item->id, $withChildren, true)) {
                $targets[self::signature($item->type, $item->ref_id, $item->options ?? [])] ??= $item;
            }
        }

        $seen = [];

        foreach ($rows as $index => $row) {
            if ($row->parent !== null || ($row->key !== null && in_array($row->key, $parents, true))) {
                continue;
            }

            $signature = self::signature($row->type, $row->ref, $row->options);

            if (isset($seen[$signature])) {
                $rows[$seen[$signature]]->qty += $row->qty;
                unset($rows[$index]);

                continue;
            }

            $seen[$signature] = $index;
        }

        $merges = [];
        $errors = [];

        foreach ($seen as $signature => $index) {
            $row = $rows[$index];
            $already = $targets[$signature] ?? null;
            $limit = $this->qtyLimit($row->line);

            if ($row->qty + ($already->qty ?? 0) > $limit) {
                $errors["items.{$index}.qty"][] = $this->word('qty', $locale, ['max' => $limit]);
            }

            if ($already !== null) {
                $merges[$index] = $already;
            }
        }

        return [$merges, $errors];
    }

    /**
     * The qty of the batch's parent a child stands under; 1 for a line without one.
     *
     * @param  array<int, BatchRow>  $rows
     */
    private function parentQty(array $rows, ?string $key): int
    {
        foreach ($rows as $row) {
            if ($key !== null && $row->key === $key) {
                return max(1, $row->qty);
            }
        }

        return 1;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, list<string>>
     */
    private function optionErrors(string $field, string $type, int $ref, array $options, string $locale): array
    {
        $provider = $this->purchasables->has($type) ? $this->purchasables->get($type) : null;

        if (! $provider instanceof ChecksOptions) {
            return [$field => [$this->word('options', $locale)]];
        }

        $messages = array_values(array_filter($provider->checkOptions($ref, $options, $locale), is_string(...)));

        return $messages === [] ? [] : [$field => $messages];
    }

    /** @param  array<string, mixed>  $options */
    private static function signature(string $type, int $ref, array $options): string
    {
        return $type.'#'.$ref.'#'.json_encode(self::normalise($options));
    }

    /** @return array<string, string> the snapshot: every language the provider gave, or this one */
    private function names(PurchasableLine $line, string $locale): array
    {
        $names = array_filter($line->names ?? [], static fn (mixed $name): bool => is_string($name) && $name !== '');

        return $names !== [] ? $names : [$locale => $line->name];
    }

    private function item(Cart $cart, int $id): CartItem
    {
        return CartItem::query()->where('cart_id', $cart->id)->whereKey($id)->firstOrFail();
    }

    private function qtyLimit(?PurchasableLine $line): int
    {
        $max = $this->limit('qty_max', 999);

        return $line?->maxQty !== null ? min($max, $line->maxQty) : $max;
    }

    private function limit(string $key, int $default): int
    {
        $value = $this->config->get("webx-cart.{$key}");

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @param  array<string, int|string>  $replace */
    private function word(string $key, string $locale, array $replace = []): string
    {
        $word = $this->translator->get("webx-cart::errors.{$key}", $replace, $locale);

        return is_string($word) ? $word : $key;
    }
}
