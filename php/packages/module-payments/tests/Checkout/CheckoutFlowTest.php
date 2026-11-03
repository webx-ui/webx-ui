<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Checkout;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Orders\Tests\Checkout\UsesFakeSlots;
use WebxUi\Orders\Tests\Checkout\VisitsCheckout;
use WebxUi\Payments\Models\PaymentSelection;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * The checkout as a buyer goes through it with the real payment step (spec §10, §12): the step
 * on the page, the choice through its API, the order awaiting payment by transfer with the bank
 * details on «Thank you» and the order's page, and «Pay at the counter» for a pick-up that does not
 * await payment.
 */
final class CheckoutFlowTest extends PaymentsCheckoutCase
{
    use UsesFakeSlots;
    use VisitsCheckout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableBuiltIns();
        $this->installSlots(shipping: true, payment: false);
    }

    #[Test]
    public function by_transfer_the_order_awaits_payment_and_thanks_shows_the_details(): void
    {
        $this->shipping->choose('carrier-a-branch', new ShippingLine(ShippingLine::FIXED, amount: 300));
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()
            ->assertSee('data-webx-payments', false)
            ->assertSee('Bank transfer')
            // A carrier without cash on delivery: no payment on receipt.
            ->assertDontSee('Payment on receipt');

        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => BankTransfer::METHOD], json: true)
            ->assertOk()
            ->assertJson(['selected' => BankTransfer::METHOD, 'summary' => 'Bank transfer', 'hint' => null]);

        $this->fill($cart);
        $this->submit($cart)->assertRedirect();
        $order = Order::query()->sole();

        $this->assertTrue($order->awaiting_payment);
        $this->assertSame('bank-transfer', $order->payment['method']);
        $this->assertSame('Shop A Ltd', $order->payment['instructions']['recipient']['en']);

        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('Bank details for payment')
            ->assertSee('Shop A Ltd')
            ->assertSee('Account 0000 0000 0000 0000, Bank A')
            ->assertSee('Payment for order '.$order->number)
            ->assertSee('Pay within 3 days');

        $this->get($this->app->make(OrderLinks::class)->page($order->number, 'en'))->assertOk()
            ->assertSee('Payment for order '.$order->number);

        // Paid, the order's page has no details to pay by any more.
        $order->forceFill(['paid_at' => now()])->save();
        $this->get($this->app->make(OrderLinks::class)->page($order->number, 'en'))->assertOk()
            ->assertDontSee('Bank details for payment');
    }

    #[Test]
    public function a_pick_up_paid_at_the_counter_does_not_await_payment(): void
    {
        $this->shipping->choose('store-pickup', new ShippingLine(ShippingLine::FREE), 'Pick up at the store', 'store', pickup: true);
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()->assertSee('Pay at the counter');
        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => OnReceipt::METHOD], json: true)
            ->assertOk()->assertJson(['summary' => 'Pay at the counter']);

        $this->fill($cart);
        $this->submit($cart)->assertRedirect();
        $order = Order::query()->sole();

        $this->assertFalse($order->awaiting_payment);
        $this->assertSame('Pay at the counter', $order->payment['title']['en']);
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('Pay at the counter')
            ->assertDontSee('Bank details for payment');
    }

    #[Test]
    public function without_a_choice_the_order_is_not_placed(): void
    {
        $this->shipping->choose('carrier-a-branch', new ShippingLine(ShippingLine::FIXED, amount: 300));
        $cart = $this->cart();
        $this->fill($cart);

        $this->submit($cart, json: true)->assertStatus(422);
        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function the_api_takes_only_a_method_offered_to_this_cart(): void
    {
        $this->shipping->choose('carrier-a-branch', new ShippingLine(ShippingLine::FIXED, amount: 300));
        $cart = $this->cart();

        // Payment on receipt is on, but not for this delivery.
        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => OnReceipt::METHOD], json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors.method.0', 'The payment method is no longer available, choose another one');
        $this->assertSame(0, PaymentSelection::query()->count());

        // A form without JavaScript goes back to the step with the refusal.
        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => 'nope'])
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->visit($cart, '/api/payments/methods', json: true, headers: ['X-Webx-Payments-View' => 'step'])
            ->assertOk()
            ->assertJsonPath('methods.0.key', BankTransfer::METHOD)
            ->assertJsonPath('selected', null)
            ->assertJsonStructure(['html']);
    }

    #[Test]
    public function without_a_cart_there_is_nothing_to_choose_for(): void
    {
        $this->getJson('/api/payments/methods')->assertNotFound();
    }
}
