<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Checkout;

use Illuminate\Foundation\Application;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;
use WebxUi\Orders\Tests\Checkout\CheckoutTestCase;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\PaymentsServiceProvider;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * The checkout of `module-orders` with payments installed: a cart of fake products in euros, the
 * two built-in methods of the migration — switched on here, the bank transfer with fake details.
 */
abstract class PaymentsCheckoutCase extends CheckoutTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PaymentsServiceProvider::class];
    }

    /** Both built-in methods on; the bank transfer with fake details in English. */
    protected function enableBuiltIns(): void
    {
        PaymentMethod::query()->where('key', OnReceipt::METHOD)->firstOrFail()->update(['enabled' => true]);
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->firstOrFail()->update([
            'enabled' => true,
            'settings' => [
                'recipient' => ['en' => 'Shop A Ltd'],
                'account' => ['en' => 'Account 0000 0000 0000 0000, Bank A'],
                'note' => ['en' => 'Pay within 3 days'],
            ],
        ]);
    }

    /** The checkout of a cart, with the delivery the test says was chosen (null — no delivery). */
    protected function checkoutOf(AddResult $cart, ?ShippingChoice $shipping = null): CheckoutContext
    {
        $state = $this->app->make(Calculator::class)->calculate($cart->cart, 'en', Purpose::Checkout);

        return new CheckoutContext($cart->cart, $state, null, null, 'en', $state->currency, static fn (): ?ShippingChoice => $shipping);
    }

    protected static function pickup(): ShippingChoice
    {
        return new ShippingChoice('store-pickup', 'store', cashOnDelivery: false, storePickup: true);
    }

    protected static function courier(bool $cod = false): ShippingChoice
    {
        return new ShippingChoice('carrier-a-branch', 'carrier-a', cashOnDelivery: $cod, storePickup: false);
    }
}
