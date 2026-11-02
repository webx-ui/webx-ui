<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Benefits;
use WebxUi\Delivery\Checkout\StepView;
use WebxUi\Delivery\Delivery;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\DeliverySnapshot;
use WebxUi\Delivery\MethodOption;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\QuoteKind;
use WebxUi\Delivery\SelectionInput;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Orders\Blocks\BlockChange;
use WebxUi\Orders\Blocks\BlockChangeRefused;
use WebxUi\Orders\Blocks\BlockChangeResult;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderBenefit;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Orders;

/**
 * A manager puts the order's delivery right after placing (decision of 02.11.2026): the buyer
 * mistyped the street, chose the wrong pick-up point, or one the carrier will not take the parcel
 * to. The locality, the method, the point or the address are checked as the checkout checks them —
 * the type's own `validate()` — and priced for the order's lines as they stand; the new snapshot
 * (§11.1) and its price go through {@see Orders::changeBlock()}, which counts the total and the
 * balance again as an edit of the lines does.
 *
 * Not while a waybill stands: the carrier has the old address. Cancel it first.
 *
 * The lines are put in a cart inside a transaction rolled back, so the methods see the parcel the
 * checkout would have seen; nothing of it is kept.
 */
final class OrderDeliveryChange
{
    /** A waybill not cancelled: the carrier was given the old delivery. */
    public const string WAYBILL = 'waybill';

    /** Received, cancelled or refunded whole. */
    public const string STATE = 'state';

    /** The order has no delivery, or nothing of it travels. */
    public const string NO_DELIVERY = 'no_delivery';

    public function __construct(
        private readonly Container $app,
        private readonly Delivery $delivery,
        private readonly Methods $methods,
        private readonly Shipments $shipments,
    ) {}

    /** Why the delivery of the order may not be changed now; null — it may. */
    public function refusal(Order $order): ?string
    {
        return match (true) {
            ! is_array($order->delivery) || $order->delivery === [] => self::NO_DELIVERY,
            $order->received_at !== null || $order->cancelled_at !== null || $order->refunded_at !== null => self::STATE,
            $this->standingWaybill($order) => self::WAYBILL,
            default => null,
        };
    }

    public function refusalText(string $refusal, string $locale): string
    {
        return (string) trans("webx-delivery::panel.order.change-refused-{$refusal}", [], $locale);
    }

    /**
     * The methods of a locality priced for the order's lines — the step's own rows (§12.2), with
     * the price in the order's currency.
     *
     * @return list<array<string, mixed>>
     */
    public function methods(Order $order, GeoLocality $locality, string $locale): array
    {
        return $this->rehearse(function () use ($order, $locality, $locale): array {
            $state = $this->state($order);
            $context = $this->delivery->context($state, $locality, Purpose::Checkout, $locale);
            $view = $this->app->make(StepView::class);

            return array_map(static fn (MethodOption $option): array => $view->method($option, $state, $locale), $this->delivery->methods($context));
        });
    }

    /**
     * Change the delivery: `locality` (an id), `method` (an id or a key), `point` or `address`
     * (`street`, `street_id`, `house`, `apartment`, `postcode`, `note`). A dry run answers the new
     * snapshot and «was → will be» of the sum, and writes nothing.
     *
     * @param  array<string, mixed>  $input
     * @return array{result: BlockChangeResult, snapshot: array<string, mixed>}
     *
     * @throws ValidationException by field: `locality`, `method`, `point`, `address.*`, `delivery`
     */
    public function change(Order $order, array $input, string $source, ?string $reason, bool $dryRun = false): array
    {
        $refusal = $this->refusal($order);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['delivery' => [$this->refusalText($refusal, $order->locale)]]);
        }

        $planned = $this->rehearse(fn (): array => $this->plan($order, $input));

        $change = new BlockChange(
            block: 'delivery',
            value: $planned['snapshot'],
            amount: $planned['amount'],
            label: (string) trans('webx-delivery::delivery.totals.delivery', [], $order->locale),
            meta: $planned['meta'],
            reason: $reason,
            source: $source,
        );

        try {
            $result = $this->app->make(Orders::class)->changeBlock($order, $change, $dryRun);
        } catch (BlockChangeRefused $refused) {
            throw ValidationException::withMessages(['delivery' => [$this->refusalText($refused->reason === BlockChangeResult::NOTHING ? 'nothing' : self::STATE, $order->locale)]]);
        }

        if ($dryRun && $result->refusal !== null) {
            throw ValidationException::withMessages(['delivery' => [$this->refusalText($result->refusal === BlockChangeResult::NOTHING ? 'nothing' : self::STATE, $order->locale)]]);
        }

        return ['result' => $result, 'snapshot' => $planned['snapshot']];
    }

    /**
     * The new snapshot and its price, from the checkout's own judges: the method as the locality
     * and the lines see it, the point or the address by the type's check.
     *
     * @param  array<string, mixed>  $input
     * @return array{snapshot: array<string, mixed>, amount: int|null, meta: array<string, mixed>}
     */
    private function plan(Order $order, array $input): array
    {
        $locale = $order->locale;
        $word = static fn (string $key): string => (string) trans("webx-delivery::delivery.errors.{$key}", [], $locale);
        $locality = is_numeric($input['locality'] ?? null)
            ? GeoLocality::query()->with('region')->whereKey((int) $input['locality'])->where('active', true)->first()
            : null;

        if ($locality === null) {
            throw ValidationException::withMessages(['locality' => [$word('choose_locality')]]);
        }

        $state = $this->state($order);

        if (! Delivery::needed($state)) {
            throw ValidationException::withMessages(['delivery' => [$this->refusalText(self::NO_DELIVERY, $locale)]]);
        }

        $context = $this->delivery->context($state, $locality, Purpose::Checkout, $locale);
        $option = $this->option($context, $input['method'] ?? null);

        if ($option === null || ! $option->offered() || $option->disabled || $option->type === null || $option->quote === null || ! $option->quote->available()) {
            throw ValidationException::withMessages(['method' => [$option?->disabled === true && $option->hint !== null ? $option->hint : $word('method_gone')]]);
        }

        $given = SelectionInput::fromArray($input);

        if ($option->type->asks() === Asks::Point && $given->point === null) {
            throw ValidationException::withMessages(['point' => [$word('choose_point')]]);
        }

        $checked = $option->type->validate($option->method, $context, $given);

        if (is_array($checked)) {
            $errors = [];

            foreach ($checked as $key => $message) {
                $errors[in_array($key, ['point', 'method', 'country', 'locality'], true) ? $key : "address.{$key}"] = [$message];
            }

            throw ValidationException::withMessages($errors);
        }

        $quote = $option->quote;
        $snapshot = DeliverySnapshot::of($checked, $quote, $order->currency)->toArray();

        return [
            'snapshot' => $snapshot,
            // As the chain prices it (§9.3): only a price the buyer pays the shop is a line.
            'amount' => $quote->inTotal && $quote->kind === QuoteKind::Fixed && (int) $quote->amount > 0 ? (int) $quote->amount : null,
            'meta' => [
                'kind' => 'delivery',
                'method' => $option->method->key,
                'type' => $option->method->type,
                'quote' => $quote->kind->value,
                'changed' => true,
            ],
        ];
    }

    private function option(DeliveryContext $context, mixed $method): ?MethodOption
    {
        if (is_numeric($method)) {
            return $this->methods->option($context, (int) $method);
        }

        if (! is_string($method) || trim($method) === '') {
            return null;
        }

        foreach ($this->methods->catalogue()['methods'] as $row) {
            if (($row['key'] ?? null) === trim($method)) {
                return $this->methods->option($context, (int) $row['id']);
            }
        }

        return null;
    }

    /**
     * The order's lines as a cart, counted as the checkout counts one: what is still in the order
     * — less what was refunded, without the lines taken out — and the rules' gifts made again by
     * the rules. The order's privilege of free shipping stays with it.
     */
    private function state(Order $order): CartState
    {
        $cart = Cart::query()->create([
            'token' => Str::random(40),
            'locale' => $order->locale,
            'currency' => $order->currency,
        ]);
        $items = OrderItem::query()->where('order_id', $order->id)->whereNull('removed_at')->orderBy('position')->orderBy('id')->get();
        $ids = [];
        $now = now();

        foreach ([null, 'children'] as $pass) {
            foreach ($items as $item) {
                $qty = $item->qty - $item->refunded_qty;

                if ($item->source !== null || $qty <= 0 || ($pass === null) !== ($item->parent_id === null)) {
                    continue;
                }

                if ($item->parent_id !== null && ! isset($ids[$item->parent_id])) {
                    continue;
                }

                $ids[$item->id] = CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'type' => $item->type,
                    'ref_id' => $item->ref_id,
                    'options' => $item->options,
                    'qty' => $qty,
                    'parent_id' => $item->parent_id !== null ? $ids[$item->parent_id] : null,
                    'name_at_add' => $item->name,
                    'price_at_add' => $item->price,
                    'added_at' => $now,
                ])->id;
            }
        }

        $state = $this->app->make(Calculator::class)->calculate($cart, $order->locale, Purpose::Checkout);
        $paid = OrderBenefit::query()->where('order_id', $order->id)->where('key', Benefits::PAID_BY_STORE)->first();

        if ($paid === null || $state->hasBenefit(Benefits::PAID_BY_STORE)) {
            return $state;
        }

        return new CartState(
            $state->currency,
            $state->locale,
            $state->lines,
            $state->lineAdjustments,
            $state->subtotal,
            $state->adjustments,
            $state->total,
            $state->tenders,
            $state->payable,
            [...$state->benefits, new Benefit(Benefits::PAID_BY_STORE, $paid->label, $paid->source)],
            $state->hints,
            $state->warnings,
            $this->app->make(Money::class),
        );
    }

    private function standingWaybill(Order $order): bool
    {
        return $this->shipments->ofOrder($order->id)->contains(
            static fn ($shipment): bool => $shipment->status !== ShipmentStatus::Cancelled && ! $shipment->final,
        );
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function rehearse(Closure $work): mixed
    {
        $db = $this->app->make(DatabaseManager::class)->connection((new Order)->getConnectionName());
        $db->beginTransaction();

        try {
            return $work();
        } finally {
            $db->rollBack();
        }
    }
}
