<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use WebxUi\Orders\Models\Order;
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
 * or in part — one milestone «paid» of what was taken — or let go; a refusal of the gateway is the
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

        // The request and the decision are one trace of the journal; capturing again is refused.
        $capture = PaymentGatewayEvent::query()->where('kind', 'capture')->sole();
        $decision = PaymentGatewayEvent::query()->where('kind', 'manual')->sole();
        $this->assertSame([$capture->trace, 'applied', 'paid'], [$decision->trace, $decision->outcome, $decision->status_after]);
        $this->expectExceptionObject(new HoldRefused(HoldRefused::NOT_HELD));
        $this->payments()->capture($payment, null, 'code');
    }

    #[Test]
    public function a_partial_capture_marks_what_was_taken_and_the_rest_is_due(): void
    {
        [$order, $payment] = $this->held();

        $this->payments()->capture($payment, 3000, 'code');

        $this->assertSame([PaymentStatus::Paid, 3000, 3000, null], [$payment->refresh()->status, $payment->capture_amount, $payment->paid_amount, $payment->problem]);
        $order->refresh();
        $this->assertSame(3000, $order->paid_total);
        $this->assertSame(1999, $this->app->make(Orders::class)->balance($order)->due);
        $this->assertSame(3000, $this->server()->payment($payment->uuid)['captured'] ?? null);
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
        $this->assertSame('rejected', PaymentGatewayEvent::query()->where('kind', 'manual')->sole()->outcome);

        // A capture that goes through puts the problem right.
        $this->payments()->capture($payment, null, 'panel');
        $this->assertSame([PaymentStatus::Paid, null], [$payment->refresh()->status, $payment->problem]);
    }

    #[Test]
    public function a_void_releases_the_hold_and_the_order_hears_of_it(): void
    {
        $failed = $this->listen(PaymentFailed::class);
        [$order, $payment] = $this->held();

        $result = $this->payments()->void($payment, 'code');

        $this->assertSame([PaymentStatus::Authorized, PaymentStatus::Cancelled], [$result->before, $result->after]);
        $this->assertSame('The hold was released by the shop', $payment->refresh()->failure_reason);
        $this->assertSame('cancelled', $this->server()->payment($payment->uuid)['status'] ?? null);
        $order->refresh();
        $this->assertNotNull($order->payment_failed_at);
        $this->assertNull($order->paid_at);
        $this->assertSame(['cancelled'], array_map(static fn (PaymentFailed $e): string => $e->status, $failed->events));
        $this->assertSame(1, PaymentGatewayEvent::query()->where('kind', 'void')->count());

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

    private function lastNote(Order $order): string
    {
        return (string) $this->app->make('db')->table('order_status_changes')->where('order_id', $order->id)->orderByDesc('id')->value('note');
    }
}
