<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Payments\MarkRefused;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Tests\Checkout\PaymentsCheckoutCase;

/**
 * A manager's «Mark as paid» of an order paid by transfer (spec §12.2): an attempt `paid` for what
 * the order owes, the milestone «paid» with the document's number, and an event `internal/manual`
 * in the exchange journal — no bodies, who and when. Once: a paid or cancelled order refuses.
 */
final class MarkPaidTest extends PaymentsCheckoutCase
{
    private function transferOrder(): Order
    {
        return $this->place($this->cart(), [
            'awaitPayment' => true,
            'blocks' => ['payment' => [
                'method' => 'bank-transfer',
                'type' => 'store.bank_transfer',
                'kind' => 'offline',
                'title' => ['en' => 'Bank transfer'],
                'price' => null,
                'instructions' => ['recipient' => ['en' => 'Shop A Ltd']],
            ]],
        ]);
    }

    #[Test]
    public function the_mark_is_a_milestone_an_attempt_and_an_event_of_the_journal(): void
    {
        $order = $this->transferOrder();
        $at = CarbonImmutable::parse('2026-11-02 10:30:00');

        $payment = $this->app->make(Payments::class)->markPaid($order, 'panel', ' PD-0001 ', $at, 'Came in this morning');

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertSame('PD-0001', $order->milestones()->where('milestone', 'paid')->value('reference'));
        $this->assertSame(0, $this->app->make(Orders::class)->balance($order)->due);

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(['bank-transfer', 'store.bank_transfer', 4999, 4999, 'EUR', 'panel'], [
            $payment->method_key, $payment->type, $payment->amount, $payment->paid_amount, $payment->currency, $payment->source,
        ]);
        $this->assertSame(['reference' => 'PD-0001'], $payment->meta);
        $this->assertTrue($at->equalTo($payment->paid_at));

        $event = PaymentGatewayEvent::query()->sole();
        $this->assertSame(['internal', 'manual', 'applied', null, 'paid'], [
            $event->direction, $event->kind, $event->outcome, $event->status_before, $event->status_after,
        ]);
        $this->assertSame([$payment->id, $order->id, 'panel'], [$event->payment_id, $event->order_id, $event->source]);
        $this->assertNull($event->request_body);
        $this->assertNull($event->response_body);
        $this->assertStringContainsString('PD-0001', (string) $event->message);
    }

    #[Test]
    public function a_paid_order_is_not_marked_twice(): void
    {
        $order = $this->transferOrder();
        $payments = $this->app->make(Payments::class);
        $payments->markPaid($order, 'panel');

        try {
            $payments->markPaid($order, 'panel');
            $this->fail('A paid order took a second mark.');
        } catch (MarkRefused $refused) {
            $this->assertSame(MarkRefused::PAID, $refused->reason);
            $this->assertSame('The order is paid already', $refused->getMessage());
        }

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, PaymentGatewayEvent::query()->count());
    }

    #[Test]
    public function a_cancelled_order_is_not_marked(): void
    {
        $order = $this->transferOrder();
        $this->app->make(Orders::class)->cancel($order, 'panel', 'The buyer changed their mind');

        $this->expectExceptionObject(new MarkRefused(MarkRefused::CANCELLED));

        try {
            $this->app->make(Payments::class)->markPaid($order, 'panel');
        } finally {
            $this->assertSame(0, Payment::query()->count());
            $this->assertSame(0, PaymentGatewayEvent::query()->count());
        }
    }
}
