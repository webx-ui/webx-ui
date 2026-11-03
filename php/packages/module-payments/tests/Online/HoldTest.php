<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use WebxUi\Orders\ItemsEdit;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Orders;
use WebxUi\Payments\Events\PaymentFailed;
use WebxUi\Payments\Events\PaymentProblem;
use WebxUi\Payments\Events\PaymentSucceeded;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\HoldRefused;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\SettleResult;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Tests\Fakes\WholeCaptureCard;

/**
 * The hold of §9.5 on gateway A: money held by «Pay» of a method with `hold`, then captured whole
 * or in part — one milestone «paid», only where what was taken covers the order — or let go; a refusal of the gateway is the
 * problem `capture_failed`, and the attempt stays held.
 */
final class HoldTest extends OnlineCase
{
    #[Test]
    public function a_full_capture_pays_the_order_once(): void
    {
        $succeeded = $this->listen(PaymentSucceeded::class);
        [$order, $payment] = $this->held();

        $result = $this->payments()->capture($payment, null, 'code');

        $this->assertSame([Outcome::Applied, PaymentStatus::Authorized, PaymentStatus::Paid], [$result->outcome, $result->before, $result->after]);
        $this->assertSame([4999, 4999], [$payment->refresh()->capture_amount, $payment->paid_amount]);
        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertSame(4999, $order->paid_total);
        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());
        $this->assertCount(1, $succeeded->events);

        // The request and the decision are one trace of the journal — a decision of its own kind,
        // not a manual mark; the order's history answers the note «capture or release it».
        $capture = PaymentGatewayEvent::query()->where('kind', 'capture')->sole();
        $decision = PaymentGatewayEvent::query()->where('kind', 'captured')->sole();
        $this->assertSame([$capture->trace, 'internal', 'applied', 'paid'], [$decision->trace, $decision->direction, $decision->outcome, $decision->status_after]);
        $this->assertSame(self::eur('49,99 € of 49,99 € by code'), $decision->message);
        $this->assertSame(0, PaymentGatewayEvent::query()->where('kind', 'manual')->count());
        $this->assertSame(self::eur('Held money captured: 49,99 € of 49,99 €'), $this->lastNote($order));
        $this->expectExceptionObject(new HoldRefused(HoldRefused::NOT_HELD));
        $this->payments()->capture($payment, null, 'code');
    }

    /**
     * A part that does not cover the order (decision of 03.11.2026, variant A): money in, no
     * milestone «paid», the status as it was — the balance says what is still to pay.
     */
    #[Test]
    public function a_partial_capture_that_does_not_cover_the_order_sets_no_milestone_and_the_rest_is_due(): void
    {
        $succeeded = $this->listen(PaymentSucceeded::class);
        [$order, $payment] = $this->held();
        $status = $order->status_id;

        $preview = $this->payments()->capture($payment, 3000, 'code', dryRun: true);
        $this->assertStringContainsString(self::eur('19,99 € stay unpaid and the order gets no milestone «paid»'), (string) $preview->message);

        $this->payments()->capture($payment, 3000, 'code');

        $this->assertSame([PaymentStatus::Paid, 3000, 3000, null], [$payment->refresh()->status, $payment->capture_amount, $payment->paid_amount, $payment->problem]);
        $order->refresh();
        $this->assertNull($order->paid_at);
        $this->assertSame(0, $order->milestones()->where('milestone', 'paid')->count());
        $this->assertSame($status, $order->status_id);
        $this->assertTrue($order->awaiting_payment);
        $this->assertSame(3000, $order->paid_total);
        $balance = $this->app->make(Orders::class)->balance($order);
        $this->assertSame([1999, 'topup'], [$balance->due, $balance->kind()]);
        $this->assertSame(3000, $this->server()->payment($payment->uuid)['captured'] ?? null);
        $this->assertCount(1, $succeeded->events);
        $this->assertSame(self::eur('Held money captured: 30,00 € of 49,99 € — 19,99 € is still to pay'), $this->lastNote($order));
    }

    /** The manager lowers the items to what is captured first: the part covers the order, the milestone is set. */
    #[Test]
    public function a_partial_capture_after_the_items_are_lowered_pays_the_order(): void
    {
        [$order, $payment] = $this->attemptAtGateway(hold: true, items: [['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 3]]);
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $this->assertSame([PaymentStatus::Authorized, 5699], [$payment->refresh()->status, $payment->authorized_amount]);

        $part = OrderItem::query()->where('order_id', $order->id)->where('ref_id', 3)->sole();
        $this->app->make(Orders::class)->editItems($order->refresh(), new ItemsEdit($order->items_revision, remove: [$part->id], reason: 'Out of stock'));
        $this->assertStringContainsString('the order gets its milestone «paid»', (string) $this->payments()->capture($payment, 4999, 'code', dryRun: true)->message);

        $this->payments()->capture($payment, 4999, 'code');

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertSame([1, 4999, 0], [$order->milestones()->where('milestone', 'paid')->count(), $order->paid_total, $this->app->make(Orders::class)->balance($order)->due]);
        $this->assertSame(4999, $this->server()->payment($payment->uuid)['captured'] ?? null);
    }

    #[Test]
    public function a_capture_the_base_will_not_ask_for_is_refused_without_a_request(): void
    {
        [, $payment] = $this->held();
        $served = $this->server()->served();

        foreach ([[0, HoldRefused::AMOUNT], [5000, HoldRefused::TOO_MUCH]] as [$amount, $reason]) {
            try {
                $this->payments()->capture($payment, $amount, 'code');
                $this->fail("{$amount} was captured.");
            } catch (HoldRefused $refused) {
                $this->assertSame($reason, $refused->reason);
            }
        }

        // A gateway that captures only the whole hold is never asked for a part (decision 52).
        $this->replaceCard(WholeCaptureCard::class);

        try {
            $this->payments()->capture($payment, 3000, 'code');
            $this->fail('A part was captured by a gateway that takes only the whole.');
        } catch (HoldRefused $refused) {
            $this->assertSame(HoldRefused::PARTIAL, $refused->reason);
        }

        $this->assertSame($served, $this->server()->served());
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $this->payments()->capture($payment, null, 'code')->after);
    }

    #[Test]
    public function a_dry_run_says_what_would_be_captured_and_asks_nobody(): void
    {
        [$order, $payment] = $this->held();
        $served = $this->server()->served();

        $result = $this->payments()->capture($payment, 2000, 'mcp', dryRun: true);

        $this->assertTrue($result->dryRun);
        $this->assertSame(PaymentStatus::Paid, $result->after);
        $this->assertStringContainsString('capture', (string) $result->message);
        $this->assertSame($served, $this->server()->served());
        $this->assertSame([PaymentStatus::Authorized, null], [$payment->refresh()->status, $payment->capture_amount]);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertSame(Outcome::Same, $this->payments()->void($payment, 'mcp', dryRun: true)->outcome);
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);
    }

    #[Test]
    public function a_capture_the_gateway_refuses_is_a_problem_and_the_money_stays_held(): void
    {
        $problems = $this->listen(PaymentProblem::class);
        [$order, $payment] = $this->held();
        $this->server()->failNext(FakeGatewayServer::CAPTURE_REFUSED);

        $result = $this->payments()->capture($payment, null, 'panel');

        $this->assertSame([PaymentStatus::Authorized, SettleResult::CAPTURE_FAILED], [$result->after, $result->problem]);
        $this->assertSame([PaymentStatus::Authorized, SettleResult::CAPTURE_FAILED], [$payment->refresh()->status, $payment->problem]);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertSame([SettleResult::CAPTURE_FAILED], array_map(static fn (PaymentProblem $e): string => $e->problem, $problems->events));
        $this->assertStringContainsString('refused to capture', $this->lastNote($order));
        $this->assertSame('rejected', PaymentGatewayEvent::query()->where('kind', 'captured')->sole()->outcome);

        // A capture that goes through puts the problem right.
        $this->payments()->capture($payment, null, 'panel');
        $this->assertSame([PaymentStatus::Paid, null], [$payment->refresh()->status, $payment->problem]);
    }

    /**
     * A hold the shop lets go is no payment that failed (decision of 03.11.2026): the order awaits
     * payment as before, without «payment failed», and its history says the hold's end.
     */
    #[Test]
    public function a_void_releases_the_hold_and_the_order_awaits_payment_as_before(): void
    {
        $failed = $this->listen(PaymentFailed::class);
        [$order, $payment] = $this->held();

        $result = $this->payments()->void($payment, 'code');

        $this->assertSame([PaymentStatus::Authorized, PaymentStatus::Cancelled], [$result->before, $result->after]);
        $this->assertSame('The hold was released by the shop', $payment->refresh()->failure_reason);
        $this->assertSame('cancelled', $this->server()->payment($payment->uuid)['status'] ?? null);
        $order->refresh();
        $this->assertNull($order->payment_failed_at);
        $this->assertTrue($order->awaiting_payment);
        $this->assertNull($order->paid_at);
        $this->assertSame(self::eur('The hold of 49,99 € was released: the money goes back to the buyer'), $this->lastNote($order));
        $this->assertSame(['cancelled'], array_map(static fn (PaymentFailed $e): string => $e->status, $failed->events));
        $this->assertSame(1, PaymentGatewayEvent::query()->where('kind', 'void')->count());
        $released = PaymentGatewayEvent::query()->where('kind', 'released')->sole();
        $this->assertSame(['internal', 'applied', self::eur('49,99 € back to the buyer by code')], [$released->direction, $released->outcome, $released->message]);

        $this->expectExceptionObject(new HoldRefused(HoldRefused::NOT_HELD));
        $this->payments()->void($payment, 'code');
    }

    #[Test]
    public function a_hold_the_gateway_gives_no_end_lasts_as_long_as_the_type_says(): void
    {
        $order = $this->onlineOrder();
        $payment = Payment::query()->create([
            'order_id' => $order->id, 'method_key' => 'gateway-a-card', 'type' => 'gateway-a.card', 'amount' => 4999,
            'currency' => 'EUR', 'status' => PaymentStatus::Pending, 'hold' => true, 'source' => 'pay-page',
        ]);
        $this->travelTo(now()->startOfMinute());

        $this->payments()->settle($payment, new GatewayStatus(Reported::Authorized, 'ga_1', 4999, 'EUR'));

        $this->assertSame(now()->addDays(7)->toIso8601String(), $payment->refresh()->hold_expires_at?->toIso8601String());
    }

    /**
     * A try that failed, then a hold on the next one (found on lab 03.11.2026): the money is held,
     * so the status strip must not say «Payment failed» — the hold takes the flag down as money in
     * does. The order still awaits payment and has no milestone «paid».
     */
    #[Test]
    public function a_hold_after_a_failed_try_takes_the_flag_of_the_failure_down(): void
    {
        $order = $this->onlineOrder();
        $create = fn (): Payment => Payment::query()->create([
            'order_id' => $order->id, 'method_key' => 'gateway-a-card', 'type' => 'gateway-a.card', 'amount' => 4999,
            'currency' => 'EUR', 'status' => PaymentStatus::Pending, 'hold' => true, 'source' => 'pay-page',
        ]);

        $this->payments()->settle($create(), new GatewayStatus(Reported::Failed, 'ga_1', 4999, 'EUR'));
        $this->assertNotNull($order->refresh()->payment_failed_at);

        $this->payments()->settle($held = $create(), new GatewayStatus(Reported::Authorized, 'ga_2', 4999, 'EUR'));

        $this->assertSame(PaymentStatus::Authorized, $held->refresh()->status);
        $order->refresh();
        $this->assertNull($order->payment_failed_at);
        $this->assertTrue($order->awaiting_payment);
        $this->assertNull($order->paid_at);
    }

    #[Test]
    public function a_method_without_hold_is_not_held(): void
    {
        [, $payment] = $this->attemptAtGateway();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $this->assertSame([false, PaymentStatus::Paid], [$payment->refresh()->hold, $payment->status]);

        $this->expectExceptionObject(new HoldRefused(HoldRefused::NOT_HELD));
        $this->payments()->capture($payment, null, 'code');
    }

    /** @return array{0: Order, 1: Payment} */
    private function held(): array
    {
        [$order, $payment] = $this->attemptAtGateway(hold: true);
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $this->assertSame([true, PaymentStatus::Authorized, 4999], [$payment->refresh()->hold, $payment->status, $payment->authorized_amount]);

        return [$order, $payment];
    }

    /** @param  class-string  $class */
    private function replaceCard(string $class): void
    {
        $types = $this->app->make(MethodTypes::class);
        $types->has('gateway-a.card');
        $property = new ReflectionProperty(MethodTypes::class, 'types');
        /** @var array<string, mixed> $all */
        $all = $property->getValue($types);
        $all['gateway-a.card'] = $this->app->make($class);
        $property->setValue($types, $all);
    }

    private function payments(): Payments
    {
        return $this->app->make(Payments::class);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $event
     * @return object{events: list<T>}
     */
    private function listen(string $event): object
    {
        $box = new class
        {
            /** @var list<object> */
            public array $events = [];
        };
        Event::listen($event, static function (object $e) use ($box): void {
            $box->events[] = $e;
        });

        return $box;
    }

    /** Money as `Money` writes it: a no-break space before the sign. */
    private static function eur(string $text): string
    {
        return str_replace(' €', "\u{00A0}€", $text);
    }

    private function lastNote(Order $order): string
    {
        return (string) $this->app->make('db')->table('order_status_changes')->where('order_id', $order->id)->orderByDesc('id')->value('note');
    }
}
