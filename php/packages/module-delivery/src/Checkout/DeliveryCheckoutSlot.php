<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Checkout;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\CartDelivery;
use WebxUi\Delivery\Delivery;
use WebxUi\Delivery\MethodOption;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\Points;
use WebxUi\Delivery\QuoteFailed;
use WebxUi\Delivery\QuoteKind;
use WebxUi\Delivery\SelectionInput;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Checkout\Slots\ShippingSlot;
use WebxUi\Orders\Checkout\Slots\SlotStep;
use WebxUi\Orders\Checkout\Steps\NamesErrors;
use WebxUi\Orders\Checkout\Steps\StepError;

/**
 * Delivery as the checkout's slot «delivery» (spec §12.3; §22.2 of the orders spec): the step is
 * `<x-webx-delivery::step>`, the choice is the cart's `delivery_selections` row, written only by
 * the delivery API. Registered by the provider when `module-orders` is installed.
 *
 * `check(Checkout)` reads the stored choice and the methods of the town; `check(Order)` checks the
 * point or the address again and prices without cache or fallback (§9.2). The order's block is
 * what was chosen — the full snapshot with the payer of the waybill comes with stage 10 (§11.1).
 */
final class DeliveryCheckoutSlot implements NamesErrors, ShippingSlot
{
    public function __construct(
        private readonly Delivery $delivery,
        private readonly Methods $methods,
    ) {}

    public function key(): string
    {
        return CheckoutSlots::DELIVERY;
    }

    public function needed(CheckoutContext $context): bool
    {
        return Delivery::needed($context->state);
    }

    public function render(CheckoutContext $context): Htmlable
    {
        $errors = view()->shared('errors');

        return new HtmlString(view('webx-delivery::step', [
            'step' => $this->view()->build($context->state, $context->cart?->id, $context->locale, is_object($errors) ? $errors : null),
            'event' => SlotStep::CHANGED,
        ])->render());
    }

    public function summary(CheckoutContext $context): ?string
    {
        $option = $this->chosen($context);

        if ($option === null) {
            return null;
        }

        $selection = $this->carts()->selection((int) $context->cart?->id);
        $locality = $selection?->locality_id !== null ? $this->carts()->locality($selection->locality_id) : null;
        $where = $locality?->name($context->locale);
        $cost = $this->delivery->cost($context->state, (int) $context->cart?->id);
        $price = $cost?->text;

        return implode(' · ', array_filter([
            implode(', ', array_filter([$option->method->title($context->locale), $where])),
            $price,
        ]));
    }

    public function errorCodes(): array
    {
        return [
            'delivery.locality' => 'no town is chosen for the delivery',
            'delivery.method' => 'no method is chosen, or the chosen one is not offered in the town any more',
            'delivery.point' => 'the method needs a point and none is chosen, or it is gone',
            'delivery.address' => 'the courier needs an address and it is not complete',
            'delivery.quote' => 'making the order, the carrier could not price the chosen method',
        ];
    }

    public function check(CheckoutContext $context, Purpose $purpose): array
    {
        $cart = $context->cart?->id;
        $selection = $cart !== null ? $this->carts()->selection($cart) : null;
        $word = static fn (string $key): string => (string) trans("webx-delivery::delivery.errors.{$key}", [], $context->locale);

        if ($selection?->locality_id === null || ($locality = $this->carts()->locality($selection->locality_id)) === null) {
            return [new StepError($this->key(), 'locality', $word('choose_locality'), 'delivery.locality')];
        }

        $deliveryContext = $this->delivery->context($context->state, $locality, $purpose, $context->locale);
        $option = $selection->method_id !== null ? $this->methods->option($deliveryContext, $selection->method_id) : null;

        if ($option === null || ! $option->offered() || $option->disabled || $option->type === null) {
            return [new StepError($this->key(), 'method', $word($selection->method_id === null ? 'choose_method' : 'method_gone'), 'delivery.method')];
        }

        $asks = $option->type->asks();

        if ($asks !== Asks::Nothing) {
            $input = $asks === Asks::Point
                ? new SelectionInput(point: $selection->point_id)
                : SelectionInput::fromArray(['address' => $selection->address ?? []]);
            $checked = $option->type->validate($option->method, $deliveryContext, $input);

            if (is_array($checked)) {
                $field = $asks === Asks::Point ? 'point' : 'address';

                return [new StepError($this->key(), $field, (string) reset($checked), 'delivery.'.$field)];
            }
        }

        if ($purpose === Purpose::Order) {
            try {
                $this->carts()->chosen($context->state, $cart, Purpose::Order);
            } catch (QuoteFailed $failed) {
                return [new StepError($this->key(), 'method', $failed->getMessage(), 'delivery.quote')];
            }
        }

        return [];
    }

    /**
     * The order's block: what was chosen, in the words of the moment.
     *
     * @return array<string, mixed>
     */
    public function snapshot(CheckoutContext $context): array
    {
        $cart = (int) $context->cart?->id;
        $selection = $this->carts()->selection($cart);
        $option = $this->chosen($context);
        $locality = $selection?->locality_id !== null ? $this->carts()->locality($selection->locality_id) : null;
        $cost = $this->delivery->cost($context->state, $cart, Purpose::Order);

        return [
            'method' => $option?->method->key,
            'type' => $option?->method->type,
            'carrier' => $option?->method->carrier(),
            'title' => $option?->method->title($context->locale),
            'country' => $selection?->country_code,
            'locality' => $locality === null ? null : ['id' => $locality->id, 'label' => $locality->label($context->locale)],
            'point' => $selection?->point_id !== null ? app(Points::class)->card($selection->point_id, $context->locale) : null,
            'address' => $selection?->address,
            'quote' => $cost?->toArray(),
        ];
    }

    public function totalsLine(CheckoutContext $context): ?ShippingLine
    {
        if ($context->cart === null) {
            return null;
        }

        $cost = $this->delivery->cost($context->state, $context->cart->id);

        if ($cost === null) {
            return null;
        }

        $quote = $cost->quote;

        return match ($quote->kind) {
            QuoteKind::Fixed => new ShippingLine(ShippingLine::FIXED, amount: (int) $quote->amount),
            QuoteKind::CarrierRates => new ShippingLine(ShippingLine::CARRIER_RATES, estimate: $quote->estimate),
            default => new ShippingLine(ShippingLine::FREE, was: $quote->was),
        };
    }

    public function choice(CheckoutContext $context): ?ShippingChoice
    {
        $option = $this->chosen($context);

        if ($option === null) {
            return null;
        }

        return new ShippingChoice(
            method: $option->method->key,
            carrier: $option->method->carrier(),
            // Cash on delivery is a carrier's feature of a point: the satellites bring it (§11).
            cashOnDelivery: false,
            storePickup: $option->method->type === StorePickup::KEY && $option->method->carrier() === Carriers::STORE,
        );
    }

    /** The request's own: the slot outlives a request, what one request read of a cart does not. */
    private function carts(): CartDelivery
    {
        return app(CartDelivery::class);
    }

    private function view(): StepView
    {
        return app(StepView::class);
    }

    private function chosen(CheckoutContext $context): ?MethodOption
    {
        return $context->cart === null ? null : $this->carts()->chosen($context->state, $context->cart->id, Purpose::Checkout);
    }
}
