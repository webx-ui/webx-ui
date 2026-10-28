<?php

declare(strict_types=1);

namespace WebxUi\Cart\Mcp;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Cart\CartRef;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Money;
use WebxUi\Cart\Purchasables;
use WebxUi\Cart\Resolver;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;

/**
 * What an agent can read of the carts (§10). Reading only: a cart is the visitor's (decision 14).
 *
 * The state is the calculator's, the one the API answers — an agent asking "what does this cart
 * cost" gets the visitor's answer, live prices and warnings included, never a second count. The
 * list does not count: it sums `price_at_add`, which is what a page of fifty carts can afford.
 *
 * The token, an IP or a cookie are in no answer: a cart is named by its id.
 *
 * `cart_quote` is `POST /api/cart/items` rehearsed ({@see Rehearsal}): the real batch into a new
 * cart, counted and rolled back, so it refuses what the visitor would be refused and costs what
 * the visitor would pay. It is a reading tool for that reason — nothing of it stays.
 */
final class CartTools
{
    private const int PER_PAGE = 20;

    /** Reading only, so one permission: there is no `cart.manage` to fall back to (decision 14). */
    private const string PERMISSION = 'cart.view';

    public function __construct(
        private readonly Calculator $calculator,
        private readonly CartService $service,
        private readonly Purchasables $purchasables,
        private readonly Resolver $resolver,
        private readonly Money $money,
        private readonly Locales $locales,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $cart = ['type' => 'integer', 'description' => 'The cart\'s id, as cart_list gives it.'];
        $locale = ['type' => 'string', 'description' => 'The language the names and warnings are in: a code of the site\'s languages.'];
        $items = [
            'type' => 'array',
            'description' => 'The lines, as POST /api/cart/items takes them: { type, ref, qty?, options?, key?, parent? }. '
                .'A child of a bundle names its parent\'s `key` in `parent`; one level deep. cart://catalog lists the types.',
            'items' => ['type' => 'object'],
        ];

        return Arguments::strictAll([
            Tool::read(
                'list',
                'Carts, the latest changed first, twenty a page: id, the visitor\'s account (`user_id`, null — a guest\'s), language, currency, lines, units, the sum at the '
                .'prices the lines were added at (not recounted — cart_get has the live total), when each was made and '
                .'last changed. Filters: changed since or before a moment, holding a record (`contains`), at least '
                .'`min_units` units.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'updated_since' => ['type' => 'string', 'description' => 'Only carts changed from this moment on, ISO 8601 or a date.'],
                    'updated_before' => ['type' => 'string', 'description' => 'Only carts last changed before this moment.'],
                    'contains' => [
                        'type' => 'object',
                        'description' => 'Only carts holding this record: { "type": "catalog.product", "ref": 12 }.',
                        'properties' => ['type' => ['type' => 'string'], 'ref' => ['type' => 'integer']],
                    ],
                    'min_units' => ['type' => 'integer', 'description' => 'Only carts with at least this many units.'],
                    'order' => ['type' => 'string', 'enum' => ['desc', 'asc'], 'description' => 'By the last change: desc (default) or asc.'],
                    'page' => ['type' => 'integer', 'description' => '1 by default.'],
                ]],
                permission: self::PERMISSION,
            ),

            Tool::read(
                'get',
                'One cart in full, counted the way the visitor sees it now: lines with live prices (a bundle\'s children '
                .'under their parent), subtotal, adjustments, total, tenders, payable, benefits, hints and warnings — every '
                .'amount in minor units with its `*_formatted` string — plus when it was made and last changed and the '
                .'rules the visitor declined, and the visitor\'s account (`user_id`, null — a guest\'s cart). In the cart\'s language unless `locale` says otherwise.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['cart' => $cart, 'locale' => $locale], 'required' => ['cart']],
                permission: self::PERMISSION,
            ),

            Tool::read(
                'summary',
                'The carts at a glance: how many there are, how many changed in the last 1, 7 and 30 days, how many are '
                .'abandoned (lines in them, untouched for `days` days), and the records in the most carts with their '
                .'names from their owners.',
                fn (array $arguments): array => $this->summary($arguments),
                ['properties' => [
                    'days' => ['type' => 'integer', 'description' => 'A cart untouched this long is abandoned; 7 by default.'],
                    'top' => ['type' => 'integer', 'description' => 'How many records to list; 10 by default, at most 50.'],
                    'locale' => $locale,
                ]],
                permission: self::PERMISSION,
            ),

            Tool::read(
                'quote',
                'What a cart of these lines would be, without making one: the lines go through every check of adding '
                .'to a cart and are counted by the same chain, then nothing is kept. Answers the state as cart_get does, '
                .'or the refusals, field by field, the visitor would get. `declined` counts the cart as if the visitor had '
                .'declined those rules\' gifts. Where to start when a discount or a gift did not apply.',
                fn (array $arguments): array => $this->quote($arguments),
                ['properties' => [
                    'items' => $items,
                    'locale' => $locale,
                    'declined' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Sources of generated lines declined: "rule#12".'],
                ], 'required' => ['items']],
                permission: self::PERMISSION,
            ),
        ], 'cart_');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = Cart::query()->select(['id', 'user_id', 'locale', 'currency', 'created_at', 'updated_at']);

        $since = $this->moment($arguments['updated_since'] ?? null, 'updated_since');
        $before = $this->moment($arguments['updated_before'] ?? null, 'updated_before');

        if ($since !== null) {
            $query->where('updated_at', '>=', $since);
        }

        if ($before !== null) {
            $query->where('updated_at', '<', $before);
        }

        if (($arguments['contains'] ?? null) !== null) {
            [$type, $ref] = $this->record($arguments['contains']);
            $query->whereHas('items', static fn (Builder $items): Builder => $items->where('type', $type)->where('ref_id', $ref));
        }

        $minUnits = $arguments['min_units'] ?? null;

        if ($minUnits !== null) {
            if (! is_int($minUnits) || $minUnits < 1) {
                throw new ToolFailure('`min_units` must be a whole number from 1.');
            }

            $units = $this->units()->whereColumn('items.cart_id', 'carts.id');
            $query->whereRaw('('.$units->toSql().') >= ?', [...$units->getBindings(), $minUnits]);
        }

        $ascending = ($arguments['order'] ?? 'desc') === 'asc';
        $query->orderBy('updated_at', $ascending ? 'asc' : 'desc')->orderBy('id', $ascending ? 'asc' : 'desc');

        $page = $query->paginate(self::PER_PAGE, ['*'], 'page', $this->page($arguments));

        /** @var list<Cart> $carts */
        $carts = $page->items();
        $totals = $this->totals(array_map(static fn (Cart $cart): int => $cart->id, $carts));

        return [
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'carts' => array_map(function (Cart $cart) use ($totals): array {
                $sums = $totals[$cart->id] ?? ['lines' => 0, 'units' => 0, 'amount' => 0];

                return [
                    'id' => $cart->id,
                    'user_id' => $cart->user_id,
                    'locale' => $cart->locale,
                    'currency' => $cart->currency,
                    'lines' => $sums['lines'],
                    'units' => $sums['units'],
                    'total_at_add' => $sums['amount'],
                    'total_at_add_formatted' => $this->money->format($sums['amount'], $cart->currency),
                    'created_at' => $cart->created_at?->toIso8601String(),
                    'updated_at' => $cart->updated_at?->toIso8601String(),
                ];
            }, $carts),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $id = $arguments['cart'] ?? null;
        $cart = is_int($id) || (is_string($id) && ctype_digit($id)) ? Cart::query()->find((int) $id) : null;

        if (! $cart instanceof Cart) {
            throw new ToolFailure('Cart '.(is_scalar($id) ? $id : '(none given)').' does not exist. cart_list lists the carts.');
        }

        $state = $this->calculator->calculate($cart, $this->locale($arguments['locale'] ?? null, $cart->locale));

        return [
            'id' => $cart->id,
            'user_id' => $cart->user_id,
            'created_at' => $cart->created_at?->toIso8601String(),
            'updated_at' => $cart->updated_at?->toIso8601String(),
            'declined_rules' => $cart->declinedRules(),
            ...$this->state($state),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function summary(array $arguments): array
    {
        $days = $this->whole($arguments['days'] ?? 7, 'days', 1, 3650);
        $top = $this->whole($arguments['top'] ?? 10, 'top', 1, 50);
        $locale = $this->locale($arguments['locale'] ?? null, null);
        $now = Carbon::now();

        $counts = Cart::query()->toBase()
            ->selectRaw('count(*) as carts')
            ->selectRaw('coalesce(sum(case when updated_at >= ? then 1 else 0 end), 0) as day', [$now->copy()->subDay()])
            ->selectRaw('coalesce(sum(case when updated_at >= ? then 1 else 0 end), 0) as week', [$now->copy()->subDays(7)])
            ->selectRaw('coalesce(sum(case when updated_at >= ? then 1 else 0 end), 0) as month', [$now->copy()->subDays(30)])
            ->first();

        $abandoned = Cart::query()->where('updated_at', '<', $now->copy()->subDays($days))->whereHas('items')->count();

        $rows = CartItem::query()->toBase()
            ->select(['type', 'ref_id'])
            ->selectRaw('count(distinct cart_id) as carts')
            ->groupBy('type', 'ref_id')
            ->orderByDesc('carts')
            ->orderBy('type')
            ->orderBy('ref_id')
            ->limit($top)
            ->get();

        // The names from their owners, one batch a type — as the calculator asks.
        $byType = [];

        foreach ($rows as $row) {
            $byType[(string) $row->type][] = new CartRef((string) $row->ref_id, (int) $row->ref_id, []);
        }

        $names = [];

        foreach ($byType as $type => $refs) {
            foreach ($this->resolver->resolve($type, $refs, $locale) as $ref => $line) {
                $names[$type][$ref] = $line?->name;
            }
        }

        return [
            'carts' => (int) ($counts->carts ?? 0),
            'active' => [
                'day' => (int) ($counts->day ?? 0),
                'week' => (int) ($counts->week ?? 0),
                'month' => (int) ($counts->month ?? 0),
            ],
            'abandoned' => ['days' => $days, 'carts' => $abandoned],
            'top' => $rows->map(static function (object $row) use ($names): array {
                $name = $names[(string) $row->type][(string) $row->ref_id] ?? null;

                return [
                    'type' => (string) $row->type,
                    'ref' => (int) $row->ref_id,
                    'name' => $name,
                    'gone' => $name === null,
                    'carts' => (int) $row->carts,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function quote(array $arguments): array
    {
        $items = $arguments['items'] ?? null;

        if (! is_array($items) || ! array_is_list($items)) {
            throw new ToolFailure('`items` must be a list of lines: [{ "type": "catalog.product", "ref": 12, "qty": 1 }].');
        }

        $declined = $arguments['declined'] ?? [];

        if (! is_array($declined) || array_filter($declined, static fn (mixed $one): bool => ! is_string($one)) !== []) {
            throw new ToolFailure('`declined` must be a list of sources: ["rule#12"].');
        }

        $locale = $this->locale($arguments['locale'] ?? null, null);

        return Rehearsal::run(function () use ($items, $declined, $locale): array {
            try {
                $cart = $this->service->add(null, $items, $locale)->cart;
            } catch (ValidationException $refused) {
                return ['accepted' => false, 'errors' => $refused->errors(), 'state' => null];
            }

            if ($declined !== []) {
                $cart->declined_rules = array_values(array_unique($declined));
                $cart->save();
            }

            return ['accepted' => true, 'errors' => [], 'state' => $this->state($this->calculator->calculate($cart, $locale))];
        });
    }

    /**
     * The answer of the API, and the `meta` the API leaves out: an agent explaining a discount
     * needs what its source said of it.
     *
     * @return array<string, mixed>
     */
    private function state(CartState $state): array
    {
        $array = $state->toArray();
        $adjustments = is_array($array['adjustments'] ?? null) ? $array['adjustments'] : [];
        $tenders = is_array($array['tenders'] ?? null) ? $array['tenders'] : [];

        foreach ($state->adjustments as $index => $adjustment) {
            $adjustments[$index] = [...(array) ($adjustments[$index] ?? []), 'meta' => $adjustment->meta];
        }

        foreach ($state->tenders as $index => $tender) {
            $tenders[$index] = [...(array) ($tenders[$index] ?? []), 'meta' => $tender->meta];
        }

        return [...$array, 'adjustments' => $adjustments, 'tenders' => $tenders];
    }

    /**
     * Lines, units and the sum at the prices of adding, for a page of carts in one query. A child
     * of a bundle counts its qty per unit of the parent, as everywhere.
     *
     * @param  list<int>  $ids
     * @return array<int, array{lines: int, units: int, amount: int}>
     */
    private function totals(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $sums = [];

        foreach ($this->items()
            ->whereIn('items.cart_id', $ids)
            ->groupBy('items.cart_id')
            ->selectRaw('items.cart_id as cart_id, count(*) as line_count')
            ->selectRaw('coalesce(sum(items.qty * coalesce(parents.qty, 1)), 0) as units')
            ->selectRaw('coalesce(sum(items.price_at_add * items.qty * coalesce(parents.qty, 1)), 0) as amount')
            ->get() as $row) {
            $sums[(int) $row->cart_id] = ['lines' => (int) $row->line_count, 'units' => (int) $row->units, 'amount' => (int) $row->amount];
        }

        return $sums;
    }

    /** The units of the cart the outer query stands on, for `min_units`. */
    private function units(): QueryBuilder
    {
        return $this->items()->selectRaw('coalesce(sum(items.qty * coalesce(parents.qty, 1)), 0)');
    }

    private function items(): QueryBuilder
    {
        return CartItem::query()->toBase()
            ->from('cart_items as items')
            ->leftJoin('cart_items as parents', 'parents.id', '=', 'items.parent_id');
    }

    /** @return array{0: string, 1: int} */
    private function record(mixed $contains): array
    {
        $type = is_array($contains) ? ($contains['type'] ?? null) : null;
        $ref = is_array($contains) ? ($contains['ref'] ?? null) : null;

        if (! is_string($type) || ! $this->purchasables->has($type)) {
            throw new ToolFailure(
                'There is no purchasable type '.(is_scalar($type) ? '"'.$type.'"' : '(none given)').'. Known: '
                .($this->purchasables->types() === [] ? 'none' : implode(', ', $this->purchasables->types())).'.'
            );
        }

        if (! is_int($ref) || $ref < 1) {
            throw new ToolFailure('`contains.ref` must be the id of the record.');
        }

        return [$type, $ref];
    }

    /** A language of the site; the fallback when none is asked, the site's default without one. */
    private function locale(mixed $asked, ?string $fallback): string
    {
        if ($asked === null || $asked === '') {
            return $fallback ?? $this->locales->defaultCode();
        }

        if (! is_string($asked) || ! $this->locales->has($asked)) {
            throw new ToolFailure(
                'The site has no language '.(is_scalar($asked) ? '"'.$asked.'"' : '(none given)').'. It has: '
                .implode(', ', $this->locales->codes()).'.'
            );
        }

        return $asked;
    }

    private function moment(mixed $value, string $name): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $refusal = new ToolFailure("`{$name}` must be a moment: ISO 8601 or a date.");

        if (! is_string($value)) {
            throw $refusal;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw $refusal;
        }
    }

    private function whole(mixed $value, string $name, int $min, int $max): int
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            throw new ToolFailure("`{$name}` must be a whole number from {$min} to {$max}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function page(array $arguments): int
    {
        $page = $arguments['page'] ?? 1;

        return is_numeric($page) ? max(1, (int) $page) : 1;
    }
}
