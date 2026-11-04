<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Checkout;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Tests\Checkout\UsesFakeSlots;
use WebxUi\Orders\Tests\Checkout\VisitsCheckout;
use WebxUi\Payments\Checkout\PaymentsCheckoutSlot;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Tests\Online\OnlineCase;
use WebxUi\Payments\Types\BankTransfer;

/**
 * An online method in the checkout (spec §8.2, §10.1): the order is placed, its attempt opened
 * without the network and the buyer sent to `/pay/go` at once; «Back» from the gateway opens no
 * second payment; a slot that cannot open the attempt leaves the buyer on «Thank you» with «Pay».
 */
final class OnlineCheckoutTest extends OnlineCase
{
    use UsesFakeSlots;
    use VisitsCheckout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableBuiltIns();
        $this->installSlots(shipping: true, payment: false);
        $this->shipping->choose('carrier-a-branch', new ShippingLine(ShippingLine::FIXED, amount: 300));
    }

    #[Test]
    public function a_card_goes_from_the_confirmation_straight_to_the_gateway(): void
    {
        $cart = $this->cart();
        $this->visit($cart, '/checkout')->assertOk()->assertSee('Gateway A');
        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => 'gateway-a-card'], json: true)->assertOk();

        $this->fill($cart);
        $placed = $this->submit($cart);
        $order = Order::query()->sole();
        $payment = Payment::query()->sole();

        // The attempt is a row without a word to the gateway: the network comes on /pay/go.
        $placed->assertRedirect($this->app->make(PaymentLinks::class)->go($payment));
        $this->assertTrue($order->awaiting_payment);
        $this->assertSame(PaymentStatus::Created, $payment->status);
        $this->assertSame('checkout', $payment->source);
        $this->assertSame($order->payable, $payment->amount);
        $this->assertSame([], $this->events());

        $this->get($this->app->make(PaymentLinks::class)->go($payment))
            ->assertRedirect($this->server()->pageUrl($payment->uuid));
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);

        // «Back» from the gateway's page: the same attempt, the page of payment — no second one.
        $this->get($this->app->make(PaymentLinks::class)->go($payment))
            ->assertRedirect($this->payPage($order));
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, collect($this->events())->filter(static fn (string $e): bool => $e === 'browser/handoff')->count());

        // Paid at the gateway: back to «Thank you» of this session.
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']))->assertRedirect();
        $this->visit($cart, '/pay/return/'.$payment->uuid)
            ->assertRedirect('/checkout/thanks/'.$order->number);
        $this->assertNotNull($order->refresh()->paid_at);
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('Payment received')
            ->assertDontSee(e($this->payPage($order)), false);
    }

    #[Test]
    public function a_transfer_stays_on_thanks_with_its_details(): void
    {
        $cart = $this->cart();
        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => BankTransfer::METHOD], json: true)->assertOk();
        $this->fill($cart);
        $order = null;

        $this->submit($cart)->assertRedirect('/checkout/thanks/'.($order = Order::query()->sole())->number);
        $this->assertSame(0, Payment::query()->count());

        // «Pay» on «Thank you» is the order's page of payment: it awaits its money.
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee(e($this->payPage($order)), false);
    }

    #[Test]
    public function next_never_throws_and_thanks_keeps_its_pay_button(): void
    {
        $order = $this->onlineOrder();
        // The attempt cannot be written: the table is gone.
        Schema::rename('payments', 'payments_gone');
        $log = Log::spy();

        $slot = $this->app->make(PaymentsCheckoutSlot::class);

        $this->assertNull($slot->next(OrderData::of($order)));
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, 'not opened'))->once();
        Schema::rename('payments_gone', 'payments');
        $this->assertSame($this->payPage($order), $slot->payAgainUrl(OrderData::of($order)));
    }

    #[Test]
    public function money_held_is_paid_for_the_buyer_and_thanks_has_no_pay(): void
    {
        $this->app->make('db')->table('payment_methods')->where('key', 'gateway-a-card')->update(['settings' => json_encode(['hold' => true])]);
        $cart = $this->cart();
        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => 'gateway-a-card'], json: true)->assertOk();
        $this->fill($cart);
        $this->submit($cart)->assertRedirect();
        $order = Order::query()->sole();
        $payment = Payment::query()->sole();
        $this->get($this->app->make(PaymentLinks::class)->go($payment));
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));

        $this->visit($cart, '/pay/return/'.$payment->uuid)->assertRedirect('/checkout/thanks/'.$order->number);
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('Payment accepted')
            // One state for the buyer (§8.3): accepted, not «waiting for payment» beside it.
            ->assertDontSee('The order is waiting for payment.')
            ->assertDontSee(e($this->payPage($order)), false);
        $this->assertTrue($order->refresh()->awaiting_payment);
        $this->assertNull($this->app->make(PaymentsCheckoutSlot::class)->payAgainUrl(OrderData::of($order)));

        // Let go by the shop, the money is no more in its hands: the order waits for payment again.
        $this->app->make(Payments::class)->void($payment->refresh(), 'code');
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()->assertSee('Awaiting payment');
    }

    #[Test]
    public function pay_again_is_for_an_order_still_awaiting_its_money(): void
    {
        $slot = $this->app->make(PaymentsCheckoutSlot::class);
        $order = $this->onlineOrder();

        $this->assertStringContainsString('signature=', (string) $slot->payAgainUrl(OrderData::of($order)));

        $order->forceFill(['cancelled_at' => now()])->save();
        $this->assertNull($slot->payAgainUrl(OrderData::of($order->refresh())));

        $paid = $this->onlineOrder();
        $paid->forceFill(['awaiting_payment' => false, 'paid_at' => now()])->save();
        $this->assertNull($slot->payAgainUrl(OrderData::of($paid->refresh())));
        $this->assertNull($slot->next(OrderData::of($paid)));
    }
}
