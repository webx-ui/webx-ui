<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\SettleResult;

/**
 * `Payments::settle()` by the table of §9.1: paid is stronger than a failure and never goes back;
 * money that is not the sum asked, for a cancelled order or for an order paid already is a problem
 * for the manager, not a milestone; a refund from the gateway's cabinet is a line in the history.
 */
final class SettleTest extends OnlineCase
{
    #[Test]
    public function a_late_paid_after_a_failure_pays_the_order(): void
    {
        [$order, $payment] = $this->pendingAttempt();
        $this->settle($payment, new GatewayStatus(Reported::Failed, reason: 'Declined'));
        $this->assertNotNull($order->refresh()->payment_failed_at);

        $result = $this->settle($payment, new GatewayStatus(Reported::Paid, 'ga_1', 4999, 'EUR'));

        $this->assertSame([Outcome::Applied, PaymentStatus::Failed, PaymentStatus::Paid, null], [$result->outcome, $result->before, $result->after, $result->problem]);
        $this->assertNotNull($order->refresh()->paid_at);
        $this->assertSame(Outcome::Same, $this->settle($payment, new GatewayStatus(Reported::Paid, 'ga_1', 4999, 'EUR'))->outcome);
    }

    #[Test]
    public function not_the_sum_asked_is_paid_with_a_problem_and_no_milestone(): void
    {
        [$order, $payment] = $this->pendingAttempt();

        $result = $this->settle($payment, new GatewayStatus(Reported::Paid, 'ga_1', 4899, 'EUR'));

        $this->assertSame([PaymentStatus::Paid, SettleResult::AMOUNT_MISMATCH], [$payment->refresh()->status, $payment->problem]);
        $this->assertSame(SettleResult::AMOUNT_MISMATCH, $result->problem);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertStringContainsString('instead of', $this->lastNote($order));
    }

    #[Test]
    public function money_for_a_cancelled_order_is_a_problem(): void
    {
        [$order, $payment] = $this->pendingAttempt();
        $this->app->make(Orders::class)->cancel($order, 'panel', 'Out of stock');

        $this->settle($payment, new GatewayStatus(Reported::Paid, 'ga_1', 4999, 'EUR'));

        $this->assertSame(SettleResult::ORDER_CANCELLED, $payment->refresh()->problem);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertStringContainsString('cancelled order', $this->lastNote($order));
    }

    #[Test]
    public function a_second_payment_of_a_paid_order_is_a_duplicate(): void
    {
        [$order, $first] = $this->pendingAttempt();
        $method = $this->app->make(Methods::class)->find('gateway-a-card');
        $this->assertNotNull($method);
        $second = $this->app->make(Payments::class)->begin($order, $method, 'pay-page');
        $second->forceFill(['status' => PaymentStatus::Pending])->save();

        $this->settle($first, new GatewayStatus(Reported::Paid, 'ga_1', 4999, 'EUR'));
        $result = $this->settle($second, new GatewayStatus(Reported::Paid, 'ga_2', 4999, 'EUR'));

        $this->assertSame(SettleResult::DUPLICATE, $result->problem);
        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());
        $this->assertSame(4999, $order->refresh()->paid_total);
        $this->assertStringContainsString('second payment', $this->lastNote($order));
    }

    #[Test]
    public function a_refund_in_the_gateways_cabinet_is_a_line_and_the_status_stays(): void
    {
        [$order, $payment] = $this->pendingAttempt();
        $this->settle($payment, new GatewayStatus(Reported::Paid, 'ga_1', 4999, 'EUR'));

        $result = $this->settle($payment, new GatewayStatus(Reported::Refunded, 'ga_1', 4999, 'EUR', refundedAmount: 1000));

        $this->assertSame([Outcome::Applied, PaymentStatus::Paid, 1000], [$result->outcome, $payment->refresh()->status, $payment->refunded_amount]);
        $this->assertStringContainsString('record the refund', $this->lastNote($order));
        $this->assertSame(Outcome::Same, $this->settle($payment, new GatewayStatus(Reported::Refunded, refundedAmount: 1000))->outcome);
    }

    #[Test]
    public function a_failure_of_an_older_attempt_does_not_flag_the_order(): void
    {
        [$order, $first] = $this->pendingAttempt();
        $method = $this->app->make(Methods::class)->find('gateway-a-card');
        $this->assertNotNull($method);
        $this->app->make(Payments::class)->begin($order, $method, 'pay-page');

        $this->settle($first, new GatewayStatus(Reported::Expired));

        $this->assertSame(PaymentStatus::Expired, $first->refresh()->status);
        $this->assertNull($order->refresh()->payment_failed_at, 'Only the last attempt speaks for the order.');
    }

    /** @return array{0: Order, 1: Payment} */
    private function pendingAttempt(): array
    {
        $order = $this->onlineOrder();
        $method = $this->app->make(Methods::class)->find('gateway-a-card');
        $this->assertNotNull($method);
        $payment = $this->app->make(Payments::class)->begin($order, $method, 'pay-page');
        $payment->forceFill(['status' => PaymentStatus::Pending])->save();

        return [$order, $payment];
    }

    private function settle(Payment $payment, GatewayStatus $status): SettleResult
    {
        return $this->app->make(Payments::class)->settle($payment, $status);
    }

    private function lastNote(Order $order): string
    {
        return (string) $this->app->make('db')->table('order_status_changes')->where('order_id', $order->id)->orderByDesc('id')->value('note');
    }
}
