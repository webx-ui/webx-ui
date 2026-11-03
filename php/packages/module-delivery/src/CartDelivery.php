<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Geography\Models\GeoLocality;

/**
 * The delivery of one cart within one request: its selection read once, and the chosen method
 * priced for the cart as it is counted (§9.3). Scoped to the request — what one request read of a
 * cart is not the next one's. Whoever writes the selection calls {@see self::forget()}.
 */
final class CartDelivery
{
    /** @var array<int, DeliverySelection|null> */
    private array $selections = [];

    /** @var array<int, GeoLocality|null> */
    private array $localities = [];

    public function __construct(
        private readonly Delivery $delivery,
        private readonly Methods $methods,
    ) {}

    public function selection(int $cart): ?DeliverySelection
    {
        if (! array_key_exists($cart, $this->selections)) {
            $this->selections[$cart] = DeliverySelection::query()->find($cart);
        }

        return $this->selections[$cart];
    }

    /** What was just written, so that the same request does not read it back (§17: the write's budget). */
    public function remember(int $cart, ?DeliverySelection $selection, ?GeoLocality $locality = null): void
    {
        $this->selections[$cart] = $selection;

        if ($locality !== null) {
            $this->localities[$locality->id] = $locality;
        }
    }

    public function forget(?int $cart = null): void
    {
        if ($cart === null) {
            $this->selections = [];
        } else {
            unset($this->selections[$cart]);
        }
    }

    /**
     * The chosen method of the cart, priced for this state of it: null — delivery is not needed,
     * nothing is chosen, or (outside of an order) the method is not offered there any more.
     *
     * @throws QuoteFailed making an order whose chosen method cannot be priced or is not offered
     */
    public function chosen(CartState $state, int $cart, Purpose $purpose): ?MethodOption
    {
        $selection = $this->selection($cart);

        if ($selection === null || $selection->method_id === null || $selection->locality_id === null || ! Delivery::needed($state)) {
            return null;
        }

        $locality = $this->locality($selection->locality_id);

        if ($locality === null) {
            return null;
        }

        $context = $this->delivery->context($state, $locality, $purpose);
        $option = $this->methods->option($context, $selection->method_id);

        if ($option === null || ! $option->offered() || $option->disabled || $option->quote === null) {
            if ($purpose === Purpose::Order) {
                throw QuoteFailed::for($option->method->key ?? (string) $selection->method_id, $context->locale);
            }

            return null;
        }

        return $option;
    }

    /** A locality with its region, read once a request. */
    public function locality(int $id): ?GeoLocality
    {
        if (! array_key_exists($id, $this->localities)) {
            $this->localities[$id] = GeoLocality::query()->with('region')->find($id);
        }

        return $this->localities[$id];
    }
}
