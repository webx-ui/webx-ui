<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Slots;

use WebxUi\Orders\Checkout\CheckoutContext;

/**
 * The delivery step (spec §22.2), `WebxUi\Delivery\Checkout\DeliveryCheckoutSlot` in
 * `module-delivery`. Its price is in the cart's chain already — an adjustment with
 * `meta.kind = delivery` — so the column of the totals takes that adjustment out of the discounts
 * and prints the line «Delivery cost» by totalsLine() instead of adding the amount again.
 */
interface ShippingSlot extends Slot
{
    /**
     * The totals line "Delivery cost" (§23.1): an amount, "Free" with the old price crossed out,
     * "at carrier rates" with an optional estimate, or null — nothing chosen.
     */
    public function totalsLine(CheckoutContext $context): ?ShippingLine;

    /** What the payment step may know about the chosen delivery (§7.3 of the payments spec); null — nothing chosen. */
    public function choice(CheckoutContext $context): ?ShippingChoice;
}
