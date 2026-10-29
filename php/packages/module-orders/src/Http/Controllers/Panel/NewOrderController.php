<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Cart\ChoiceOption;
use WebxUi\Cart\Money;
use WebxUi\Cart\PurchasableChoice;
use WebxUi\Cart\Purchasables;
use WebxUi\Localization\Locales;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Checkout\CheckoutPreview;
use WebxUi\Orders\Contact;
use WebxUi\Orders\ManagerOrder;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;

/**
 * «New order» (spec §29.2): a manager takes an order by phone. The products are found through
 * the cart's providers (`Purchasables::find()`), the totals are the service's preview counted
 * live, and the order is `Orders::placeByManager()` with `source = panel` — this class reads the
 * form and turns the answers and the refusals into the panel's words; the order is the service's.
 */
final readonly class NewOrderController
{
    /** Enough to choose from by a word; a manager types more rather than scrolls. */
    private const int FOUND = 20;

    public function __construct(
        private Orders $orders,
        private Purchasables $purchasables,
        private Locales $locales,
        private Money $money,
    ) {}

    /** The languages an order may be in (its names and its letters); the site's own first. */
    public function meta(): JsonResponse
    {
        return ApiResponse::data([
            'locales' => $this->locales->all()->map(static fn ($locale): array => ['code' => $locale->code, 'name' => $locale->name])->values()->all(),
            'locale' => $this->locales->defaultCode(),
        ]);
    }

    /** What can be put in a line, found by a word: every provider that can be searched. */
    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:190'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        $locale = $this->locale($validated['locale'] ?? null);
        $found = [];

        foreach ($this->purchasables->find((string) ($validated['search'] ?? ''), $locale, self::FOUND) as $type => $choices) {
            foreach ($choices as $choice) {
                $found[] = $this->choice($type, $choice);
            }
        }

        return ApiResponse::data(array_slice($found, 0, self::FOUND));
    }

    /**
     * The lines counted as `place()` would count them, before the contacts are typed: the totals,
     * the warnings of the chain, and whether it can be ordered. A line the cart refuses comes back
     * as an error under it (`items.1.ref`), like the order would.
     */
    public function preview(Request $request): JsonResponse
    {
        $input = $this->input($request, contacts: false);
        $preview = $this->orders->previewByManager($input, contacts: false);

        return ApiResponse::data([
            'orderable' => $preview->orderable(),
            'message' => $preview->refusal !== null ? CheckoutPreview::notOrderable($preview->refusal) : null,
            'totals' => $preview->state->toArray(),
        ]);
    }

    /** The order itself; the card opens on it. The form's token makes a second press the first order. */
    public function store(Request $request): JsonResponse
    {
        $input = $this->input($request, contacts: true);

        try {
            $order = $this->orders->placeByManager($input, 'panel');
        } catch (CartNotOrderable $refused) {
            throw ValidationException::withMessages(['items' => CheckoutPreview::notOrderable($refused)]);
        } catch (OrderRefused $refused) {
            throw ValidationException::withMessages(['items' => $refused->getMessage()]);
        }

        return ApiResponse::data(['id' => (int) $order->id, 'number' => $order->number], 201);
    }

    private function input(Request $request, bool $contacts): ManagerOrder
    {
        $validated = $request->validate([
            'lines' => ['present', 'array', 'list'],
            'lines.*' => ['array:type,ref,qty,options'],
            'lines.*.type' => ['required', 'string', 'max:64'],
            'lines.*.ref' => ['required', 'integer'],
            'lines.*.qty' => ['nullable', 'integer', 'min:1'],
            'lines.*.options' => ['nullable', 'array'],
            'locale' => ['nullable', 'string', 'max:16'],
            'customer' => ['nullable', 'array'],
            'recipient' => ['nullable', 'array'],
            'comment' => ['nullable', 'string'],
            'key' => ['nullable', 'string', 'max:190'],
        ]);

        $customer = (array) ($validated['customer'] ?? []);
        $recipient = $validated['recipient'] ?? null;

        return new ManagerOrder(
            lines: array_map(static fn (array $line): array => array_filter($line, static fn (mixed $value): bool => $value !== null), $validated['lines']),
            locale: $this->locale($validated['locale'] ?? null),
            customer: self::contact($customer),
            // A recipient left empty is «the buyer takes it», not a recipient without a name.
            recipient: $contacts && is_array($recipient) && array_filter($recipient, static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '') !== []
                ? self::contact($recipient)
                : null,
            comment: self::text($validated['comment'] ?? null),
            idempotencyKey: self::text($validated['key'] ?? null),
        );
    }

    private function locale(?string $asked): string
    {
        if ($asked === null || $asked === '') {
            return $this->locales->defaultCode();
        }

        if (! $this->locales->has($asked)) {
            throw ValidationException::withMessages(['locale' => (string) __('validation.in', ['attribute' => 'locale'])]);
        }

        return $asked;
    }

    /**
     * @return array<string, mixed>
     */
    private function choice(string $type, PurchasableChoice $choice): array
    {
        $line = $choice->line;

        return [
            'type' => $type,
            'ref' => $choice->ref,
            'name' => $line->name,
            'sku' => $line->sku,
            'hint' => $choice->hint,
            'image' => $line->image,
            'price' => $line->price,
            'price_formatted' => $line->price > 0 ? $this->money->format($line->price, $line->currency) : null,
            'available' => $line->buyable(),
            'options' => array_map(static fn (ChoiceOption $option): array => [
                'key' => $option->key,
                'label' => $option->label,
                'values' => $option->values,
            ], $choice->options),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function contact(array $values): Contact
    {
        return new Contact(
            (string) self::text($values['first_name'] ?? null),
            self::text($values['last_name'] ?? null),
            self::text($values['phone'] ?? null),
            self::text($values['email'] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
