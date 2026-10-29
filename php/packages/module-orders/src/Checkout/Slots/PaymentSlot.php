<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Slots;

use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Data\OrderData;

/**
 * The payment step (spec §22.3), `PaymentsCheckoutSlot` in `module-payments`. It comes after
 * delivery (300 after 200): a method may depend on the chosen delivery, which it reads only as
 * `CheckoutContext::shippingChoice()`.
 */
interface PaymentSlot extends Slot
{
    /** Whether the chosen method is paid online after the order exists (§5.1). */
    public function awaitsPayment(CheckoutContext $context): bool;

    /**
     * Where the buyer goes right after the order is placed: a gateway page, or null — the thanks page.
     * Called after commit; must not throw for a placed order — on failure it returns null and logs.
     */
    public function next(OrderData $order): ?string;

    /** "Pay now" on the thanks page and the order page for an order that still awaits payment. */
    public function payAgainUrl(OrderData $order): ?string;
}
