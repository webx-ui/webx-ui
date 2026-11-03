<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\SettleResult;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\FakeGatewaySite;

/**
 * «Capture» and «Release the hold» of the tab «Payment» (spec §9.5, §13.4; decision 32): the
 * order's action — `orders.manage`; a dry run says what would move and asks the gateway nothing;
 * a part where the gateway takes one; a refusal of the gateway leaves the money held.
 */
final class HoldPanelTest extends PanelCase
{
    #[Test]
    public function the_tab_offers_the_hold_to_whoever_works_the_orders(): void
    {
        [$order, $payment] = $this->held();

        $this->signIn(['payments.view']);
        $this->getJson($this->api('orders/'.$order->id))->assertOk()
            ->assertJsonPath('data.state', 'held')
            ->assertJsonPath('data.attempts.0.can_capture', false)
            ->assertJsonPath('data.attempts.0.partial', true)
            ->assertJsonPath('data.attempts.0.amount_decimal', '49.99');
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), [])->assertForbidden();
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/void'), [])->assertForbidden();

        $this->signIn(['payments.view', 'orders.manage']);
        $this->getJson($this->api('orders/'.$order->id))->assertJsonPath('data.attempts.0.can_capture', true);
    }

    #[Test]
    public function a_dry_run_says_what_moves_and_asks_the_gateway_nothing(): void
    {
        [$order, $payment] = $this->held();
        $this->signIn(['payments.view', 'orders.manage']);
        $events = PaymentGatewayEvent::query()->count();

        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), ['amount' => '30,00', 'dry_run' => true])->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.amount', 3000)
            ->assertJsonPath('data.held', 4999)
            ->assertJsonPath('data.released', 1999);
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/void'), ['dry_run' => true])->assertOk()
            ->assertJsonPath('data.held', 4999);

        // More than is held, and an amount that is not one: refused by field, before the gateway.
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), ['amount' => '60', 'dry_run' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), ['amount' => '1.234'])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertSame($events, PaymentGatewayEvent::query()->count());
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);
        $this->assertNull($order->refresh()->paid_at);
    }

    #[Test]
    public function a_part_is_captured_and_the_order_is_paid_with_it(): void
    {
        [$order, $payment] = $this->held();
        $this->signIn(['payments.view', 'orders.manage']);

        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), ['amount' => '30.00'])->assertOk()
            ->assertJsonPath('data.state', 'paid')
            ->assertJsonPath('data.attempts.0.status', 'paid')
            ->assertJsonPath('data.attempts.0.can_capture', false);

        $this->assertSame([PaymentStatus::Paid, 3000, 'panel'], [$payment->refresh()->status, $payment->paid_amount, PaymentGatewayEvent::query()->where('kind', 'capture')->sole()->source]);
        $this->assertSame(3000, $order->refresh()->paid_total);
    }

    #[Test]
    public function the_hold_is_released(): void
    {
        [$order, $payment] = $this->held();
        $this->signIn(['payments.view', 'orders.manage']);

        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/void'), [])->assertOk()
            ->assertJsonPath('data.attempts.0.status', 'cancelled');
        $this->assertSame(PaymentStatus::Cancelled, $payment->refresh()->status);
        $this->assertNull($order->refresh()->paid_at);

        // Nothing is held any more: both actions say so.
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/void'), [])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
    }

    #[Test]
    public function a_refused_capture_keeps_the_money_held_and_says_so(): void
    {
        [$order, $payment] = $this->held();
        $this->signIn(['payments.view', 'orders.manage']);
        $this->server()->failNext(FakeGatewayServer::CAPTURE_REFUSED);

        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment.0', 'The gateway refused the capture: the money is still held. The exchange journal has its answer.');

        $this->assertSame([PaymentStatus::Authorized, SettleResult::CAPTURE_FAILED], [$payment->refresh()->status, $payment->problem]);
    }

    #[Test]
    public function an_attempt_of_another_order_is_not_found(): void
    {
        [, $payment] = $this->held();
        $other = $this->onlineOrder();
        $this->signIn(['payments.view', 'orders.manage']);

        $this->postJson($this->api('orders/'.$other->id.'/attempts/'.$payment->id.'/capture'), [])->assertNotFound();
    }

    /** @return array{0: Order, 1: Payment} */
    private function held(): array
    {
        [$order, $payment] = $this->attemptAtGateway(hold: true);
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);

        return [$order, $payment];
    }
}
