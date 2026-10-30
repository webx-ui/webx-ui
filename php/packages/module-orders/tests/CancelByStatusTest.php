<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Orders;

/**
 * A status that cancels the order is a cancellation (§9.1, decision of 29.10.2026): the panel and an
 * agent give its reason in the note, as they do to `cancel()` — on a dry run too — and the reason
 * is in the history and on the milestone.
 */
final class CancelByStatusTest extends PlaceTestCase
{
    private function cancelling(): OrderStatus
    {
        return OrderStatus::query()->where('sets_milestone', Milestone::Cancelled->value)->firstOrFail();
    }

    #[Test]
    public function a_move_into_a_cancelling_status_wants_a_reason_from_the_panel_and_an_agent(): void
    {
        $order = $this->place($this->cart());
        $orders = $this->app->make(Orders::class);

        foreach (['panel', 'mcp'] as $source) {
            foreach ([true, false] as $dryRun) {
                $writes = 0;
                DB::listen(static function ($query) use (&$writes): void {
                    $writes += preg_match('/^\s*(insert|update|delete)/i', $query->sql) === 1 ? 1 : 0;
                });

                try {
                    $orders->moveTo($order, $this->cancelling(), $source, '  ', $dryRun);
                    $this->fail("{$source} moved into «Cancelled» without a reason.");
                } catch (ValidationException $refused) {
                    $this->assertArrayHasKey('note', $refused->errors());
                }

                $this->assertSame(0, $writes);
            }
        }

        $this->assertNull($order->refresh()->cancelled_at);
    }

    #[Test]
    public function with_a_reason_the_move_cancels_and_the_reason_is_in_the_history(): void
    {
        $order = $this->place($this->cart());
        $orders = $this->app->make(Orders::class);

        $preview = $orders->moveTo($order, $this->cancelling(), 'panel', 'The buyer changed their mind', true);
        $this->assertSame(Milestone::Cancelled, $preview->milestone);

        $orders->moveTo($order, $this->cancelling(), 'panel', 'The buyer changed their mind');

        $this->assertNotNull($order->refresh()->cancelled_at);
        $this->assertSame('The buyer changed their mind', OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'cancelled')->value('note'));
        $this->assertSame('The buyer changed their mind', StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::STATUS)->latest('id')->value('note'));
    }

    #[Test]
    public function the_site_and_an_order_cancelled_already_need_no_reason(): void
    {
        $orders = $this->app->make(Orders::class);

        $byApi = $this->place($this->cart());
        $orders->moveTo($byApi, $this->cancelling(), 'api');
        $this->assertNotNull($byApi->refresh()->cancelled_at);

        // Cancelled already, and taken out of «Cancelled» by hand: the way back sets nothing, so
        // it asks for nothing.
        $cancelled = $this->place($this->cart());
        $orders->cancel($cancelled, 'panel', 'Duplicate');
        $orders->moveTo($cancelled, OrderStatus::query()->orderBy('id')->firstOrFail(), 'panel');
        $move = $orders->moveTo($cancelled, $this->cancelling(), 'panel');
        $this->assertTrue($move->moved);
        $this->assertNull($move->milestone);
    }
}
