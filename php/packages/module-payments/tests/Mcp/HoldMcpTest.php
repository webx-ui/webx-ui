<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Testing\FakeGatewaySite;

/**
 * `payments_capture` and `payments_void` (spec §9.5, §16; decision 32): the buttons of the tab for
 * the agent — a dry run asks the gateway nothing, a part where the gateway takes one, the
 * refusals in words, and both behind `orders.manage`.
 */
final class HoldMcpTest extends McpCase
{
    #[Test]
    public function a_dry_run_then_a_part_is_captured(): void
    {
        [$order, $payment] = $this->held();
        $events = PaymentGatewayEvent::query()->count();

        $dry = $this->use('payments_capture', ['payment' => $payment->uuid, 'amount' => '20.00', 'dry_run' => true]);
        $this->assertSame([true, 'authorized', 'paid'], [$dry['dry_run'], $dry['status_before'], $dry['status_after']]);
        $this->assertSame($events, PaymentGatewayEvent::query()->count());
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);

        $done = $this->use('payments_capture', ['payment' => $payment->uuid, 'amount' => '20.00']);
        $this->assertSame(['paid', 'paid', true], [$done['status_after'], $done['payment']['status'], $done['changed']]);
        $this->assertSame(2000, $order->refresh()->paid_total);
        $this->assertSame('mcp', PaymentGatewayEvent::query()->where('kind', 'capture')->sole()->source);

        $this->refused('payments_capture', ['payment' => $payment->uuid], 'No money is held by this payment');
    }

    #[Test]
    public function the_hold_is_released_and_refusals_are_words(): void
    {
        [, $payment] = $this->held();

        $this->refused('payments_capture', ['payment' => $payment->uuid, 'amount' => '99.00'], 'More than is held');
        $this->refused('payments_capture', ['payment' => $payment->uuid, 'amount' => 'ten'], 'is a decimal in EUR');

        $dry = $this->use('payments_void', ['payment' => $payment->uuid, 'dry_run' => true]);
        $this->assertSame([true, 'cancelled'], [$dry['dry_run'], $dry['status_after']]);
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);

        $this->use('payments_void', ['payment' => $payment->id]);
        $this->assertSame(PaymentStatus::Cancelled, $payment->refresh()->status);
    }

    #[Test]
    public function both_are_the_orders_action(): void
    {
        [, $payment] = $this->held();

        $this->agent('payments_capture', ['payment' => $payment->uuid], ['payments.view', 'payments.manage'])->assertHasErrors();
        $this->agent('payments_void', ['payment' => $payment->uuid], ['payments.view', 'payments.manage'])->assertHasErrors();
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);
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
