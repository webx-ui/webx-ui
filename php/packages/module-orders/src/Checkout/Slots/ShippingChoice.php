<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Slots;

/**
 * The chosen delivery in neutral words (spec §22.2): built by the shipping slot, read by the
 * payment slot through `CheckoutContext::shippingChoice()` — so neither module requires the other
 * nor calls the other's API. A cash-on-delivery method, say, is offered only when the carrier
 * collects money at this point.
 */
final readonly class ShippingChoice
{
    /**
     * @param  string  $method  delivery method key: 'store-pickup', 'carrier-a-branch'
     * @param  string  $carrier  'store' or a carrier key
     * @param  bool  $cashOnDelivery  the carrier collects money on handover for this method and point
     * @param  bool  $storePickup  the buyer collects it at the shop's own point
     */
    public function __construct(
        public string $method,
        public string $carrier,
        public bool $cashOnDelivery,
        public bool $storePickup,
    ) {}
}
