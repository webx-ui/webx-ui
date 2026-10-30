<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderPublicStatusChanged;
use WebxUi\Orders\Events\OrderReceived;
use WebxUi\Orders\Events\OrderStatusChanged;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Orders;
use WebxUi\Orders\StatusMove;
use WebxUi\Orders\StatusMoveRefused;

/** Moving an order between statuses (§5.2): freely, with the milestone a status sets, and the events of §7.2. */
final class StatusMoveTest extends PlaceTestCase
{
    /** @var list<string> the events after the commit, in the order they came */
    private array $seen = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([OrderStatusChanged::class, OrderPublicStatusChanged::class, OrderPaid::class, OrderReceived::class, OrderCancelled::class] as $event) {
            Event::listen($event, function (object $event): void {
                $this->seen[] = class_basename($event);
            });
        }
    }

    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    /** A status of the default set by its English name. */
    private function named(string $name): OrderStatus
    {
        return OrderStatus::withTrashed()->get()->firstOrFail(static fn (OrderStatus $status): bool => $status->getTranslation('name', 'en') === $name);
    }

    #[Test]
    public function a_move_inside_a_public_group_changes_nothing_the_buyer_sees(): void
    {
        $order = $this->place($this->cart());
        $events = [];
        Event::listen(OrderStatusChanged::class, static function (OrderStatusChanged $event) use (&$events): void {
            $events[] = $event;
        });

        $move = $this->orders()->moveTo($order, $this->named('Awaiting payment'), 'panel', '  Asked for an invoice ');

        $this->assertTrue($move->moved);
        $this->assertFalse($move->publicChanged);
        $this->assertSame([], $move->notifications);
        $this->assertSame(['New', 'Awaiting payment'], [$move->from->name, $move->to->name]);
        $this->assertSame($this->named('Awaiting payment')->id, $order->status_id);
        $this->assertSame(['OrderStatusChanged'], $this->seen);
        $this->assertFalse($events[0]->publicChanged);
        $this->assertSame('Asked for an invoice', $events[0]->note);
        $this->assertSame('Awaiting payment', $events[0]->order->status->name);

        $line = StatusChange::query()->where('order_id', $order->id)->orderByDesc('id')->firstOrFail();
        $accepted = PublicStatus::query()->where('code', 'accepted')->value('id');
        $this->assertSame(
            [StatusChange::STATUS, $this->named('New')->id, $this->named('Awaiting payment')->id, $accepted, $accepted, 'panel', 'Asked for an invoice'],
            [$line->kind, $line->from_status_id, $line->to_status_id, $line->from_public_id, $line->to_public_id, $line->source, $line->note],
        );
    }

    #[Test]
    public function a_move_into_another_group_tells_the_buyer_through_its_channels(): void
    {
        $order = $this->place($this->cart());
        PublicStatus::query()->where('code', 'in_progress')->sole()->update(['options' => ['notify_channels' => [4, 9]]]);
        $public = [];
        Event::listen(OrderPublicStatusChanged::class, static function (OrderPublicStatusChanged $event) use (&$public): void {
            $public[] = [$event->from->code, $event->to->code, $event->order->publicStatus->code];
        });

        $move = $this->orders()->moveTo($order, $this->named('Being packed'), 'panel');

        $this->assertTrue($move->publicChanged);
        $this->assertSame([4, 9], $move->notifications);
        $this->assertSame(['accepted', 'in_progress'], [$move->publicFrom->code, $move->publicTo->code]);
        $this->assertSame(['OrderStatusChanged', 'OrderPublicStatusChanged'], $this->seen);
        $this->assertSame([['accepted', 'in_progress', 'in_progress']], $public);

        // Back is a move like any other.
        $this->orders()->moveTo($order, $this->named('New'), 'panel');
        $this->assertSame($this->named('New')->id, $order->status_id);
        $this->assertCount(4, $this->seen);
    }

    #[Test]
    public function a_status_with_a_milestone_sets_it_once_with_its_own_source_after_the_status_events(): void
    {
        $order = $this->place($this->cart());
        $completed = $this->named('Completed');

        $move = $this->orders()->moveTo($order, $completed, 'panel');

        $this->assertSame(Milestone::Received, $move->milestone);
        $this->assertSame(['OrderStatusChanged', 'OrderPublicStatusChanged', 'OrderReceived'], $this->seen);
        $this->assertSame("status#{$completed->id}", OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'received')->sole()->source);
        $this->assertNotNull($order->received_at);

        // Out and in again: the milestone is there, so the move sets nothing.
        $this->seen = [];
        $this->orders()->moveTo($order, $this->named('Being packed'), 'panel');
        $again = $this->orders()->moveTo($order, $completed, 'panel');

        $this->assertNull($again->milestone);
        $this->assertNotContains('OrderReceived', $this->seen);
        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'received')->count());
    }

    #[Test]
    public function a_status_whose_milestone_the_order_cannot_take_refuses_the_whole_move(): void
    {
        $order = $this->place($this->cart());
        $this->orders()->moveTo($order, $this->named('Cancelled'), 'panel', 'The buyer asked');
        $cancelled = $this->named('Cancelled');
        $this->seen = [];
        $lines = StatusChange::query()->where('order_id', $order->id)->count();

        try {
            $this->orders()->moveTo($order, $this->named('Completed'), 'panel');
            $this->fail('A cancelled order came into «Completed».');
        } catch (StatusMoveRefused $refusal) {
            $this->assertSame(StatusMove::CANCELLED, $refusal->reason);
            $this->assertSame(Milestone::Received, $refusal->move->milestone);
        }

        $this->assertSame($cancelled->id, Order::query()->findOrFail($order->id)->status_id);
        $this->assertSame($lines, StatusChange::query()->where('order_id', $order->id)->count());
        $this->assertSame([], $this->seen);
        // The reason of the cancel went with the milestone.
        $this->assertSame('The buyer asked', OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'cancelled')->sole()->note);

        // A status without a milestone is fine: a cancelled order is still moved about.
        $this->assertTrue($this->orders()->moveTo($order, $this->named('Being packed'), 'panel')->moved);
    }

    #[Test]
    public function a_dry_run_answers_as_the_move_would_and_writes_nothing(): void
    {
        $order = $this->place($this->cart());
        PublicStatus::query()->where('code', 'completed')->sole()->update(['options' => ['notify_channels' => [2]]]);
        $completed = $this->named('Completed');
        $writes = [];
        DB::listen(static function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete)/i', $query->sql) === 1) {
                $writes[] = $query->sql;
            }
        });

        $dry = $this->orders()->moveTo($order, $completed, 'panel', 'Done', dryRun: true);

        $this->assertSame([], $writes);
        $this->assertSame([], $this->seen);
        $this->assertTrue($dry->dryRun);
        $this->assertSame($this->named('New')->id, Order::query()->findOrFail($order->id)->status_id);
        $this->assertNull(Order::query()->findOrFail($order->id)->received_at);

        // A refusal comes as an answer, not as an exception.
        $off = $this->named('Being packed');
        $off->update(['is_active' => false]);
        $writes = [];
        $this->assertSame(StatusMove::INACTIVE, $this->orders()->moveTo($order, $off, 'panel', dryRun: true)->refusal);
        $this->assertSame([], $writes);

        $real = $this->orders()->moveTo($order, $completed, 'panel', 'Done');
        $this->assertEquals([$dry->from, $dry->to, $dry->publicFrom, $dry->publicTo, $dry->publicChanged, $dry->milestone, $dry->notifications], [$real->from, $real->to, $real->publicFrom, $real->publicTo, $real->publicChanged, $real->milestone, $real->notifications]);
        $this->assertSame([2], $real->notifications);
    }

    #[Test]
    public function the_same_status_is_silence_and_a_switched_off_or_deleted_one_is_refused(): void
    {
        $order = $this->place($this->cart());
        $lines = StatusChange::query()->where('order_id', $order->id)->count();

        $same = $this->orders()->moveTo($order, $this->named('New'), 'panel');
        $this->assertFalse($same->moved);
        $this->assertSame([], $this->seen);

        $off = $this->named('Being packed');
        $off->update(['is_active' => false]);
        $gone = $this->named('Waiting for a call');
        $gone->delete();

        foreach ([[$off, StatusMove::INACTIVE], [$gone, StatusMove::DELETED]] as [$status, $reason]) {
            try {
                $this->orders()->moveTo($order, $status, 'panel');
                $this->fail("The order came into a status that is {$reason}.");
            } catch (StatusMoveRefused $refusal) {
                $this->assertSame($reason, $refusal->reason);
            }
        }

        $this->assertSame($lines, StatusChange::query()->where('order_id', $order->id)->count());
        $this->assertSame([], $this->seen);
    }

    #[Test]
    public function an_order_leaves_a_deleted_status_and_the_channels_of_a_deleted_group_are_quiet(): void
    {
        $order = $this->place($this->cart());
        $this->orders()->moveTo($order, $this->named('Handed to delivery'), 'panel');
        $this->named('Handed to delivery')->delete();
        $completedGroup = PublicStatus::query()->where('code', 'completed')->sole();
        $completedGroup->update(['options' => ['notify_channels' => [5]]]);
        $completedGroup->delete();

        $move = $this->orders()->moveTo($order, $this->named('Completed'), 'panel');

        $this->assertTrue($move->moved);
        $this->assertSame([], $move->notifications);
        $this->assertSame('shipped', $move->publicFrom->code);
    }

    #[Test]
    public function a_status_that_sets_paid_takes_the_awaiting_flag_down(): void
    {
        $order = $this->place($this->cart(), ['awaitPayment' => true]);
        $packed = $this->named('Being packed');
        $packed->update(['sets_milestone' => Milestone::Paid]);

        $this->orders()->moveTo($order, $packed, 'mcp');

        $this->assertFalse($order->awaiting_payment);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(['OrderStatusChanged', 'OrderPublicStatusChanged', 'OrderPaid'], $this->seen);
    }
}
