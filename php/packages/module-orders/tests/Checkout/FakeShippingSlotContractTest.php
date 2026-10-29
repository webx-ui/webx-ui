<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Checkout\Slots\Slot;
use WebxUi\Orders\Tests\Fixtures\FakeShippingSlot;

/** The delivery the checkout is tested with keeps the contract the real one does (§22.4). */
final class FakeShippingSlotContractTest extends SlotContractTestCase
{
    protected function slot(): Slot
    {
        return new FakeShippingSlot;
    }

    protected function choose(Slot $slot, CheckoutContext $context): void
    {
        $this->assertInstanceOf(FakeShippingSlot::class, $slot);
        $slot->choose('courier', new ShippingLine(ShippingLine::FIXED, 500));
    }
}
