<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Cart\Totals\TenderSources;
use WebxUi\Orders\Events\OrderBlockUpdated;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderDetailsChanged;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderPaymentFailed;
use WebxUi\Orders\Events\OrderPublicStatusChanged;
use WebxUi\Orders\Events\OrderReceived;
use WebxUi\Orders\Events\OrderStatusChanged;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\MilestoneMark;
use WebxUi\Orders\MilestoneRefused;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderCancelledAlready;
use WebxUi\Orders\OrderPaidAlready;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Tests\Fakes\FakeSources;

/** Milestones (§5.1), a failed payment (§5.4) and an order that stops awaiting payment (§5.5). */
final class MilestonesTest extends PlaceTestCase
{
    /** Every event of the module after the commit. */
    private const array EVENTS = [
        OrderPaid::class, OrderReceived::class, OrderCancelled::class, OrderPaymentFailed::class,
        OrderStatusChanged::class, OrderPublicStatusChanged::class, OrderBlockUpdated::class, OrderDetailsChanged::class,
    ];

    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    private function awaiting(): Order
    {
        return $this->place($this->cart(), ['awaitPayment' => true]);
    }

    #[Test]
    public function paid_is_set_once_takes_the_flags_down_and_says_so_after_the_commit(): void
    {
        $events = [];
        Event::listen(OrderPaid::class, static function (OrderPaid $event) use (&$events): void {
            $events[] = $event;
        });
        $order = $this->awaiting();
        $this->orders()->paymentFailed($order, 'payment:gateway-a', 'Declined');
        // Later than the order: the timeline goes by time.
        $at = CarbonImmutable::now()->addMinute()->startOfSecond();

        $mark = $this->orders()->mark($order, Milestone::Paid, 'payment:gateway-a', $at, 'PAY-1');

        $this->assertTrue($mark->marked());
        $this->assertSame('payment:gateway-a', $mark->source);
        // The model passed in is the order as it is now.
        $this->assertFalse($order->awaiting_payment);
        $this->assertNull($order->payment_failed_at);
        $this->assertSame($at->toIso8601String(), $order->paid_at?->toIso8601String());

        $row = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'paid')->sole();
        $this->assertSame(['payment:gateway-a', 'PAY-1', $at->toIso8601String()], [$row->source, $row->reference, $row->at->toIso8601String()]);
        // «Awaiting payment» stays in the history of milestones.
        $this->assertSame(['placed', 'awaiting_payment', 'paid'], OrderMilestone::query()->where('order_id', $order->id)->orderBy('id')->pluck('milestone')->map->value->all());

        $this->assertCount(1, $events);
        $this->assertSame('PAY-1', $events[0]->reference);
        $this->assertSame(['placed', 'awaiting_payment', 'paid'], array_keys($events[0]->order->milestones));
    }

    #[Test]
    public function a_repeated_milestone_is_silence_and_answers_with_the_first_mark(): void
    {
        Event::fake([OrderReceived::class]);
        $order = $this->place($this->cart());
        $orders = $this->orders();

        $first = $orders->mark($order, Milestone::Received, 'panel', CarbonImmutable::parse('2026-10-28 09:00:00'), note: 'Picked up');
        $again = $orders->mark($order, Milestone::Received, 'delivery:carrier-a', reference: 'WB-7');

        $this->assertTrue($first->marked());
        $this->assertTrue($again->alreadyMarked());
        $this->assertSame(['panel', 'Picked up', null], [$again->source, $again->note, $again->reference]);
        $this->assertSame('2026-10-28T09:00:00+00:00', $again->at->toIso8601String());
        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'received')->count());
        Event::assertDispatchedTimes(OrderReceived::class, 1);
    }

    #[Test]
    public function two_marks_of_paid_from_models_read_before_either_make_one_event(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->awaiting();
        // A gateway and a manager, each with the order as it was before the other one marked it.
        $gateway = Order::query()->findOrFail($order->id);
        $manager = Order::query()->findOrFail($order->id);

        $one = $this->orders()->mark($gateway, Milestone::Paid, 'payment:gateway-a');
        $two = $this->orders()->mark($manager, Milestone::Paid, 'panel');

        $this->assertTrue($one->marked());
        $this->assertTrue($two->alreadyMarked());
        $this->assertSame('payment:gateway-a', $two->source);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'paid')->count());
    }

    #[Test]
    public function placed_awaiting_payment_and_refunded_are_not_set_by_hand(): void
    {
        $order = $this->place($this->cart());

        foreach ([Milestone::Placed, Milestone::AwaitingPayment, Milestone::Refunded] as $milestone) {
            try {
                $this->orders()->mark($order, $milestone, 'panel');
                $this->fail("{$milestone->value} was set by hand.");
            } catch (MilestoneRefused $refusal) {
                $this->assertSame('not_markable', $refusal->reason);
            }
        }

        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function a_cancelled_order_is_neither_paid_nor_received_and_one_given_back_whole_is_not_cancelled(): void
    {
        Event::fake([OrderPaid::class, OrderReceived::class, OrderCancelled::class]);
        $order = $this->awaiting();
        $this->orders()->mark($order, Milestone::Cancelled, 'panel', note: 'The buyer asked');

        foreach ([Milestone::Paid, Milestone::Received] as $milestone) {
            try {
                $this->orders()->mark($order, $milestone, 'payment:gateway-a');
                $this->fail("{$milestone->value} was set on a cancelled order.");
            } catch (OrderCancelledAlready $refusal) {
                $this->assertSame('cancelled', $refusal->reason);
            }
        }

        Event::assertNotDispatched(OrderPaid::class);
        Event::assertNotDispatched(OrderReceived::class);

        // Given back whole — the refund is the next stage's; its milestone copy is enough here.
        $refunded = $this->place($this->cart());
        Order::query()->whereKey($refunded->id)->update(['refunded_at' => CarbonImmutable::parse('2026-10-29 12:00:00')]);
        $mark = $this->orders()->mark($refunded, Milestone::Cancelled, 'panel');

        $this->assertSame(MilestoneMark::REFUNDED, $mark->outcome);
        $this->assertNull($refunded->cancelled_at);
        $this->assertFalse(OrderMilestone::query()->where('order_id', $refunded->id)->where('milestone', 'cancelled')->exists());
        Event::assertDispatchedTimes(OrderCancelled::class, 1);
    }

    #[Test]
    public function cancelled_gives_the_tenders_back_and_tells_whether_the_order_was_paid(): void
    {
        $this->app->make(TenderSources::class)->register(new FakeSources(static fn (): array => [
            new Tender('wallet', 'Certificate', 1000, ['batches' => [3]]),
            new Tender('wallet', 'Cashback', 200),
        ]));
        $events = [];
        Event::listen(OrderCancelled::class, static function (OrderCancelled $event) use (&$events): void {
            $events[] = $event;
        });
        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]));
        $this->orders()->mark($order, Milestone::Paid, 'panel');
        // A part of the cashback went back by a refund before — the next stage's; a column is enough.
        OrderTender::query()->where('order_id', $order->id)->where('label', 'Cashback')->update(['refunded_amount' => 50]);

        $this->orders()->mark($order, Milestone::Cancelled, 'panel', note: 'Out of stock');

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertTrue($event->wasPaid);
        $this->assertSame('Out of stock', $event->reason);
        $this->assertSame([['Certificate', 1000, ['batches' => [3]]], ['Cashback', 150, []]], array_map(
            static fn ($tender): array => [$tender->label, $tender->amount, $tender->meta],
            $event->tendersReturned,
        ));
        $this->assertSame([1000, 200], OrderTender::query()->where('order_id', $order->id)->orderBy('id')->pluck('refunded_amount')->all());
        $this->assertSame([1000, 200], array_map(static fn ($tender): int => $tender->refundedAmount, $event->order->tenders));

        // A second cancel is silence: nothing is returned twice.
        $this->assertTrue($this->orders()->mark($order, Milestone::Cancelled, 'mcp')->alreadyMarked());
        $this->assertCount(1, $events);
    }

    #[Test]
    public function a_failed_payment_is_a_history_line_and_a_flag_not_a_milestone(): void
    {
        $events = [];
        Event::listen(OrderPaymentFailed::class, static function (OrderPaymentFailed $event) use (&$events): void {
            $events[] = $event;
        });
        $order = $this->awaiting();

        $this->orders()->paymentFailed($order, 'payment:gateway-a', '  Card declined  ');
        $this->orders()->paymentFailed($order, 'payment:gateway-a');

        $this->assertNotNull($order->payment_failed_at);
        $this->assertTrue($order->awaiting_payment);
        $lines = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::PAYMENT_FAILED)->orderBy('id')->get();
        $this->assertSame([['payment:gateway-a', 'Card declined'], ['payment:gateway-a', null]], $lines->map(fn (StatusChange $line): array => [$line->source, $line->note])->all());
        $this->assertSame(2, OrderMilestone::query()->where('order_id', $order->id)->count());
        $this->assertSame(['Card declined', null], array_map(static fn (OrderPaymentFailed $event): ?string => $event->reason, $events));

        // A late «declined» after the payment came is history, and the flag stays down.
        $this->orders()->mark($order, Milestone::Paid, 'payment:gateway-a');
        $this->orders()->paymentFailed($order, 'payment:gateway-a', 'Late');
        $this->assertNull(Order::query()->findOrFail($order->id)->payment_failed_at);
    }

    #[Test]
    public function stop_awaiting_payment_takes_the_flag_down_with_a_history_line_and_no_event(): void
    {
        $order = $this->awaiting();
        Event::fake(self::EVENTS);

        $this->orders()->stopAwaitingPayment($order, 'payment:cash');
        // Not awaiting any more: silence, no second line.
        $this->orders()->stopAwaitingPayment($order, 'payment:cash');

        $this->assertFalse($order->awaiting_payment);
        $this->assertFalse(Order::query()->findOrFail($order->id)->awaiting_payment);
        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::PAYMENT_AWAITING_STOPPED)->sole();
        $this->assertSame('payment:cash', $line->source);
        $this->assertTrue(OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'awaiting_payment')->exists());
        foreach (self::EVENTS as $event) {
            Event::assertNotDispatched($event);
        }
    }

    #[Test]
    public function stop_awaiting_payment_is_refused_for_a_paid_and_a_cancelled_order(): void
    {
        $paid = $this->awaiting();
        $this->orders()->mark($paid, Milestone::Paid, 'panel');
        $cancelled = $this->awaiting();
        $this->orders()->mark($cancelled, Milestone::Cancelled, 'panel');

        foreach ([[$paid, OrderPaidAlready::class, 'paid'], [$cancelled, OrderCancelledAlready::class, 'cancelled']] as [$order, $class, $reason]) {
            try {
                $this->orders()->stopAwaitingPayment($order, 'payment:cash');
                $this->fail('A '.$reason.' order stopped awaiting payment.');
            } catch (OrderPaidAlready|OrderCancelledAlready $refusal) {
                $this->assertInstanceOf($class, $refusal);
                $this->assertSame($reason, $refusal->reason);
            }

            $this->assertFalse(StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::PAYMENT_AWAITING_STOPPED)->exists());
        }

        // The cancelled one keeps its flag: cancelling is not paying.
        $this->assertTrue(Order::query()->findOrFail($cancelled->id)->awaiting_payment);
    }
}
