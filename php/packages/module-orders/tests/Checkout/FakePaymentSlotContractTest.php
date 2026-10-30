<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\Slot;
use WebxUi\Orders\Tests\Fixtures\FakePaymentSlot;

/** The payment the checkout is tested with keeps the contract the real one does (§22.4). */
final class FakePaymentSlotContractTest extends SlotContractTestCase
{
    protected function slot(): Slot
    {
        $slot = new FakePaymentSlot;
        $slot->gateway = 'https://pay.example.test/go';

        return $slot;
    }

    protected function choose(Slot $slot, CheckoutContext $context): void
    {
        $this->assertInstanceOf(FakePaymentSlot::class, $slot);
        $slot->choose('card', online: true);
    }
}
