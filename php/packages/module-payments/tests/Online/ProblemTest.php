<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Events\PaymentProblem;
use WebxUi\Payments\Events\PaymentSucceeded;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\ProblemRefused;
use WebxUi\Payments\Reported;
use WebxUi\Payments\SettleResult;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * The problems of §9.4 — an event for the manager's notice, «resolved» with a note that keeps the
 * problem on the attempt — and the cash a carrier collects for an order «on receipt» (§11.3).
 */
final class ProblemTest extends OnlineCase
{
    #[Test]
    public function a_second_payment_is_a_problem_event_and_the_first_a_success(): void
    {
        $problems = [];
        $successes = [];
        Event::listen(PaymentProblem::class, static function (PaymentProblem $e) use (&$problems): void {
            $problems[] = $e;
        });
        Event::listen(PaymentSucceeded::class, static function (PaymentSucceeded $e) use (&$successes): void {
            $successes[] = $e;
        });
        $order = $this->onlineOrder();
        [$first, $second] = [$this->pending($order, 'ga_1'), $this->pending($order, 'ga_2')];

        $this->payments()->settle($first, new GatewayStatus(Reported::Paid, 'ga_1', 4999, 'EUR'));
        $this->payments()->settle($second, new GatewayStatus(Reported::Paid, 'ga_2', 4999, 'EUR'));

        $this->assertSame([$first->id], array_map(static fn (PaymentSucceeded $e): int => $e->paymentId, $successes));
        $this->assertCount(1, $problems);
        $this->assertSame([$second->id, SettleResult::DUPLICATE, 4999, 'ga_2'], [$problems[0]->paymentId, $problems[0]->problem, $problems[0]->amount, $problems[0]->gatewayRef]);
        $this->assertStringContainsString('give it back', $problems[0]->message);
    }

    #[Test]
    public function a_resolved_problem_stays_on_the_attempt_with_who_and_why(): void
    {
        $order = $this->onlineOrder();
        $payment = $this->pending($order, 'ga_1');
        $this->payments()->settle($payment, new GatewayStatus(Reported::Paid, 'ga_1', 4899, 'EUR'));
        $this->assertSame(SettleResult::AMOUNT_MISMATCH, $payment->refresh()->problem);

        $this->assertSame(SettleResult::AMOUNT_MISMATCH, $this->payments()->resolveProblem($payment, 'panel', 'Marked by hand', dryRun: true)->problem);
        $this->assertArrayNotHasKey('resolved_at', $payment->refresh()->meta ?? []);

        $this->payments()->resolveProblem($payment, 'panel', 'Marked by hand');

        $payment->refresh();
        $this->assertSame([PaymentStatus::Paid, SettleResult::AMOUNT_MISMATCH], [$payment->status, $payment->problem]);
        $this->assertSame(['panel', 'Marked by hand'], [$payment->meta['resolved_source'] ?? null, $payment->meta['resolved_note'] ?? null]);
        $this->assertNotNull($payment->meta['resolved_at'] ?? null);
        $this->assertStringContainsString('is resolved: Marked by hand', (string) $this->app->make('db')->table('order_status_changes')->where('order_id', $order->id)->orderByDesc('id')->value('note'));
    }

    #[Test]
    public function an_attempt_without_a_problem_or_with_a_holds_one_is_not_resolved(): void
    {
        $order = $this->onlineOrder();
        $payment = $this->pending($order, 'ga_1');

        try {
            $this->payments()->resolveProblem($payment, 'panel');
            $this->fail('An attempt without a problem was resolved.');
        } catch (ProblemRefused $refused) {
            $this->assertSame(ProblemRefused::NONE, $refused->reason);
        }

        $payment->forceFill(['status' => PaymentStatus::Authorized, 'problem' => SettleResult::HOLD_EXPIRING])->save();
        $this->expectExceptionObject(new ProblemRefused(ProblemRefused::HOLD));
        $this->payments()->resolveProblem($payment, 'panel');
    }

    #[Test]
    public function the_carrier_collects_what_an_order_on_receipt_owes(): void
    {
        $order = $this->place($this->cart(), [
            'awaitPayment' => false,
            'blocks' => ['payment' => ['method' => OnReceipt::METHOD, 'type' => OnReceipt::KEY, 'kind' => 'offline', 'title' => ['en' => 'On receipt'], 'price' => null]],
        ]);
        $courier = ['method' => 'courier', 'type' => 'carrier-a.courier', 'carrier' => 'carrier-a'];
        $pickup = ['method' => 'store-pickup', 'type' => 'store.pickup', 'carrier' => 'store'];

        $this->assertSame(4999, $this->payments()->cashOnDelivery($this->data($order, $courier)));
        $this->assertNull($this->payments()->cashOnDelivery($this->data($order, $pickup)), 'Picked up at the shop: the shop takes the money.');
        $this->assertNull($this->payments()->cashOnDelivery($this->data($order, null)), 'Nothing is delivered.');

        $transfer = $this->data($order, $courier, ['method' => BankTransfer::METHOD, 'type' => BankTransfer::KEY, 'kind' => 'offline']);
        $this->assertNull($this->payments()->cashOnDelivery($transfer), 'Paid by transfer, not to the carrier.');

        $this->app->make(Payments::class)->markPaid($order, 'panel');
        $this->assertNull($this->payments()->cashOnDelivery($this->data($order->refresh(), $courier)), 'Nothing is due.');
    }

    private function pending(Order $order, string $ref): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id, 'method_key' => 'gateway-a-card', 'type' => 'gateway-a.card', 'amount' => 4999,
            'currency' => 'EUR', 'status' => PaymentStatus::Pending, 'gateway_ref' => $ref, 'source' => 'pay-page',
        ]);
    }

    /**
     * The order as an event carries it, with a delivery block of its own.
     *
     * @param  array<string, mixed>|null  $delivery
     * @param  array<string, mixed>|null  $payment
     */
    private function data(Order $order, ?array $delivery, ?array $payment = null): OrderData
    {
        $copy = $order->replicate();
        $copy->id = $order->id;
        $copy->setAttribute('delivery', $delivery);

        if ($payment !== null) {
            $copy->setAttribute('payment', $payment);
        }

        return OrderData::of($copy);
    }

    private function payments(): Payments
    {
        return $this->app->make(Payments::class);
    }
}
