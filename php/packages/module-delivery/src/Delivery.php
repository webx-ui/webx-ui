<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Cart\Totals\ResolvedLine;
use WebxUi\Geography\Models\GeoLocality;

/**
 * The questions the checkout asks the delivery module first: is delivery needed at all
 * (decision 5), and what a method of a locality is given to work with.
 */
final class Delivery
{
    public function __construct(private readonly Methods $methods) {}

    /** No line that travels — a service, a certificate — no delivery step. */
    public static function needed(CartState $cart): bool
    {
        return self::shippableLines($cart) !== [];
    }

    /**
     * Lines that can be bought and travel. A line whose record is gone does not.
     *
     * @return list<ResolvedLine>
     */
    public static function shippableLines(CartState $cart): array
    {
        return array_values(array_filter(
            $cart->lines,
            static fn (ResolvedLine $line): bool => $line->available && $line->line !== null && $line->line->shippable,
        ));
    }

    /** The context of a locality for this cart: the locality is read once, with its region. */
    public function context(CartState $cart, GeoLocality|int $locality, Purpose $purpose = Purpose::Checkout, ?string $locale = null): DeliveryContext
    {
        if (is_int($locality)) {
            $locality = GeoLocality::query()->with('region')->find($locality)
                ?? throw new DeliveryException("No locality with id {$locality}.");
        }

        return new DeliveryContext(
            country: $locality->country_code,
            locality: LocalityRef::of($locality),
            carrierLocality: null,
            cart: $cart,
            locale: $locale ?? $cart->locale,
            currency: (string) $cart->currency,
            purpose: $purpose,
        );
    }

    /**
     * What the buyer is offered in the locality (§6.3).
     *
     * @return list<MethodOption>
     */
    public function methods(DeliveryContext $context): array
    {
        return $this->methods->forLocality($context);
    }
}
