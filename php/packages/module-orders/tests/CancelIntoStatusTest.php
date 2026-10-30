<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Orders\Doctor\OrdersCancelStatusCheck;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderPublicStatusChanged;
use WebxUi\Orders\Events\OrderStatusChanged;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Orders;

/**
 * A cancellation moves the order into the first active status that cancels (§9.1, decision 88):
 * the buyer sees it cancelled, the move is a move — its history line with the reason, its events
 * before the cancellation's — and a site without such a status keeps the order where it stood,
 * which `webx:doctor` says.
 */
final class CancelIntoStatusTest extends PlaceTestCase
{
    /** @var list<string> */
    private array $seen = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([OrderStatusChanged::class, OrderPublicStatusChanged::class, OrderCancelled::class] as $event) {
            Event::listen($event, function (object $event): void {
                $this->seen[] = class_basename($event);
            });
        }
    }

    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    private function cancelling(): OrderStatus
    {
        return OrderStatus::query()->where('sets_milestone', Milestone::Cancelled->value)->firstOrFail();
    }

    #[Test]
    public function the_cancelled_order_comes_into_the_status_that_cancels(): void
    {
        $order = $this->place($this->cart());
        $from = $order->status_id;

        $mark = $this->orders()->cancel($order, 'panel', 'The buyer changed their mind');

        $order->refresh();
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame($this->cancelling()->id, $order->status_id);
        $this->assertSame('cancelled', $order->status->publicStatus->code);

        $this->assertNotNull($mark->move);
        $this->assertSame($from, $mark->move->from->id);
        $this->assertTrue($mark->move->publicChanged);
        // The milestone is the cancellation's own, with its source — not «status#…».
        $this->assertNull($mark->move->milestone);
        $this->assertSame('panel', $order->milestones()->where('milestone', 'cancelled')->value('source'));

        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::STATUS)->latest('id')->firstOrFail();
        $this->assertSame($from, $line->from_status_id);
        $this->assertSame($order->status_id, $line->to_status_id);
        $this->assertSame('The buyer changed their mind', $line->note);
        $this->assertSame('panel', $line->source);

        // As a move into that status has them (§7.2): the status, the public one, the milestone.
        $this->assertSame(['OrderStatusChanged', 'OrderPublicStatusChanged', 'OrderCancelled'], $this->seen);
    }

    #[Test]
    public function a_dry_run_names_the_status_and_writes_nothing(): void
    {
        $order = $this->place($this->cart());
        $from = $order->status_id;
        $writes = 0;
        DB::listen(static function ($query) use (&$writes): void {
            $writes += preg_match('/^\s*(insert|update|delete)/i', $query->sql) === 1 ? 1 : 0;
        });

        $mark = $this->orders()->cancel($order, 'mcp', 'Duplicate', true);

        $this->assertSame(0, $writes);
        $this->assertSame([], $this->seen);
        $this->assertSame($this->cancelling()->id, $mark->move?->to->id);
        $this->assertNull($mark->move->milestone);
        $this->assertSame($from, $order->refresh()->status_id);
    }

    #[Test]
    public function an_order_cancelled_already_is_not_moved(): void
    {
        $order = $this->place($this->cart());
        $this->orders()->cancel($order, 'api');
        $this->assertSame($this->cancelling()->id, $order->refresh()->status_id);

        // The manager takes it back to «New» by hand; cancelling again is silence and moves nothing.
        $new = OrderStatus::query()->orderBy('id')->firstOrFail();
        $this->orders()->moveTo($order, $new, 'api');
        $this->seen = [];

        $mark = $this->orders()->cancel($order, 'panel', 'Again', true);
        $this->assertTrue($mark->alreadyMarked());
        $this->assertNull($mark->move);

        $this->orders()->cancel($order, 'panel', 'Again');
        $this->assertSame($new->id, $order->refresh()->status_id);
        $this->assertSame([], $this->seen);
    }

    #[Test]
    public function without_a_status_that_cancels_the_order_stays_and_the_doctor_warns(): void
    {
        $this->assertSame('ok', $this->app->make(OrdersCancelStatusCheck::class)->run()[0]->level());
        $this->assertContains(OrdersCancelStatusCheck::class, $this->app->make(DoctorChecks::class)->all());

        $this->cancelling()->update(['is_active' => false]);

        $order = $this->place($this->cart());
        $from = $order->status_id;
        $mark = $this->orders()->cancel($order, 'panel', 'Duplicate');

        $this->assertNull($mark->move);
        $this->assertNotNull($order->refresh()->cancelled_at);
        $this->assertSame($from, $order->status_id);
        $this->assertSame(['OrderCancelled'], $this->seen);

        $diagnoses = $this->app->make(OrdersCancelStatusCheck::class)->run();
        $this->assertCount(1, $diagnoses);
        $this->assertSame('content', $diagnoses[0]->level());
        $this->assertStringContainsString('no active status sets the mark "Cancelled"', $diagnoses[0]->detail);
    }
}
