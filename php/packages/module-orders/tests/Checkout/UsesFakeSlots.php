<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Orders\Blocks\BlockDefinition;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Tests\Fixtures\FakePaymentSlot;
use WebxUi\Orders\Tests\Fixtures\FakeShippingSlot;

/**
 * Delivery and payment installed as fakes, registered the way their modules do it (§22.1): the
 * slot after the registry resolves, the block of the order, and the delivery's price as the
 * chain's adjustment with priority 1000.
 */
trait UsesFakeSlots
{
    protected FakeShippingSlot $shipping;

    protected FakePaymentSlot $payment;

    protected function installSlots(bool $shipping = true, bool $payment = true): void
    {
        $this->shipping = new FakeShippingSlot;
        $this->payment = new FakePaymentSlot;

        // The registries are built on the first ask: a test's slots come before it.
        $this->app->forgetInstance(CheckoutSteps::class);
        $this->app->forgetInstance(CheckoutSlots::class);
        $this->app->afterResolving(CheckoutSlots::class, function (CheckoutSlots $slots) use ($shipping, $payment): void {
            if ($shipping) {
                $slots->register($this->shipping);
            }

            if ($payment) {
                $slots->register($this->payment);
            }
        });

        $blocks = $this->app->make(OrderBlocks::class);

        if ($shipping) {
            $blocks->register(new BlockDefinition('delivery', 'delivery', static fn (array $value): array => isset($value['method']) ? [] : ['method is required']));
            $this->app->make(AdjustmentSources::class)->register($this->shipping, 1000);
        }

        if ($payment) {
            $blocks->register(new BlockDefinition('payment', 'payments', static fn (array $value): array => isset($value['method']) ? [] : ['method is required']));
        }
    }
}
