<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Cart\Money;
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

    /**
     * The price of what the buyer chose, as the chain counts it (§9.1, §9.4): the base's quote of
     * the method — cached for a satellite, «Free» under `shipping.paid_by_store` — or
     * `unavailable` with the reason the method is not offered there now.
     *
     * @throws QuoteFailed making an order, when the carrier could not price it
     */
    public function quoteFor(Selection $selection, DeliveryContext $context): Quote
    {
        $option = $this->methods->option($context, $selection->method->id);

        if ($option !== null && $option->offered() && ! $option->disabled && $option->quote !== null) {
            return $option->quote;
        }

        if ($option?->quote !== null && ! $option->quote->available()) {
            return $option->quote;
        }

        return Quote::unavailable($option?->reason() ?? MethodOption::NOT_AVAILABLE);
    }

    /**
     * The order's block `delivery` (§11.1): the checked choice and its price for the order, copied
     * in every language — the checkout calls it after `validate()` passed again for the order.
     */
    public function snapshot(Selection $selection, Quote $quote, ?string $currency = null): DeliverySnapshot
    {
        return DeliverySnapshot::of($selection, $quote, $currency);
    }

    /**
     * The totals line «Delivery cost» of the cart (decision 7): null — delivery is not needed or
     * nothing is chosen, or the chosen method is not offered there any more.
     *
     * @throws QuoteFailed making an order whose chosen method cannot be priced
     */
    public function cost(CartState $state, int $cart, Purpose $purpose = Purpose::Checkout): ?DeliveryCost
    {
        $chosen = app(CartDelivery::class)->chosen($state, $cart, $purpose);

        if ($chosen === null || $chosen->quote === null) {
            return null;
        }

        $quote = $chosen->quote;
        $locale = $state->locale;
        $money = app(Money::class);
        $format = static fn (?int $minor): ?string => $minor === null
            ? null
            : ($state->currency === null ? (string) $minor : $money->format($minor, $state->currency));
        $word = static fn (string $key): string => (string) trans("webx-delivery::delivery.step.{$key}", [], $locale);

        return new DeliveryCost(
            method: $chosen->method->key,
            quote: $quote,
            label: $word('cost'),
            text: match ($quote->kind) {
                QuoteKind::Fixed => (string) $format($quote->amount),
                QuoteKind::CarrierRates => $word('carrier_rates'),
                default => $word('free'),
            },
            amountFormatted: $format($quote->amount),
            wasFormatted: $format($quote->was),
            estimateFormatted: $format($quote->estimate),
        );
    }
}
