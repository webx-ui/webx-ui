<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Cart\Totals\ResolvedLine;

/** What a method of a type is given each time: never a model, never the database (§6.1). */
final readonly class DeliveryContext
{
    public function __construct(
        /** ISO 3166-1. */
        public string $country,
        public LocalityRef $locality,
        /** The carrier's own id for the locality; null — not bound, or the shop's own method. */
        public ?string $carrierLocality,
        public CartState $cart,
        public string $locale,
        public string $currency,
        public Purpose $purpose,
    ) {}

    /** The same context for a method of another carrier, with that carrier's id of the locality. */
    public function forCarrier(?string $carrierLocality): self
    {
        return new self($this->country, $this->locality, $carrierLocality, $this->cart, $this->locale, $this->currency, $this->purpose);
    }

    /**
     * Lines with shippable = true that can be bought — what travels.
     *
     * @return list<ResolvedLine>
     */
    public function shippableLines(): array
    {
        return Delivery::shippableLines($this->cart);
    }

    public function paidByStore(): bool
    {
        return $this->cart->hasBenefit(Benefits::PAID_BY_STORE);
    }
}
