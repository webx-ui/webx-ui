<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Checkout;

use Illuminate\Foundation\Application;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\Slot;
use WebxUi\Orders\Tests\Checkout\SlotContractTestCase;
use WebxUi\Payments\Checkout\PaymentsCheckoutSlot;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\PaymentsServiceProvider;
use WebxUi\Payments\Selections;
use WebxUi\Payments\Types\BankTransfer;

/** The real payment step keeps the contract of the checkout's slots (§22 of the orders spec). */
final class PaymentsSlotContractTest extends SlotContractTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PaymentsServiceProvider::class];
    }

    protected function slot(): Slot
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->firstOrFail()->update([
            'enabled' => true,
            'settings' => ['recipient' => ['en' => 'Shop A Ltd'], 'account' => ['en' => 'Account 0000']],
        ]);

        return new PaymentsCheckoutSlot($this->app->make(Methods::class));
    }

    protected function choose(Slot $slot, CheckoutContext $context): void
    {
        $this->app->make(Selections::class)->put((int) $context->cart?->id, BankTransfer::METHOD);
    }
}
