<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;

/**
 * What a method is asked against (spec §6.1): never a model, never the database.
 */
final readonly class PaymentContext
{
    /**
     * @param  int  $payable  «to pay», minor units; the only amount payments sees
     * @param  ShippingChoice|null  $shipping  null — no delivery step, no module-delivery, or nothing chosen yet (§7.3)
     * @param  Purpose  $purpose  the cart's Purpose: Checkout | Order (the pay page asks as Order)
     * @param  bool  $shippingPending  the checkout has a delivery step the buyer has not filled yet:
     *                                 the methods that depend on the delivery wait for it (§7.3)
     */
    public function __construct(
        public int $payable,
        public string $currency,
        public string $locale,
        public ?ShippingChoice $shipping,
        public Purpose $purpose,
        public bool $shippingPending = false,
    ) {}
}
