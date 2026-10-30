<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Cart\Totals\TenderSources;
use WebxUi\Orders\Events\OrderRefunded;
use WebxUi\Orders\Events\OrderRefundReversed;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderRefund;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Orders;
use WebxUi\Orders\RefundRequest;
use WebxUi\Orders\RefundReversal;
use WebxUi\Orders\RefundReversalRefused;
use WebxUi\Orders\Tests\Fakes\FakeSources;

/**
 * A refund recorded by mistake is reversed, never edited (decision 98, §9.5): the row stays, its
 * sums come off the order, «refunded» comes off when the order is no longer given back whole, and
 * the right one is recorded after it as any refund.
 */
final class RefundReversalTest extends PlaceTestCase
{
    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    /** Two units of «Item A» (2 × 49.99), paid, with a certificate of 10.00 counted against it. */
    private function paidWithCertificate(): Order
    {
        $this->app->make(TenderSources::class)->register(new FakeSources(static fn (): array => [new Tender('wallet', 'Certificate', 1000, ['batches' => [3]])]));
        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]));
        $this->orders()->mark($order, Milestone::Paid, 'panel');

        return $order;
    }

    private function tender(Order $order): OrderTender
    {
        return OrderTender::query()->where('order_id', $order->id)->sole();
    }

    private function item(Order $order): OrderItem
    {
        return OrderItem::query()->where('order_id', $order->id)->sole();
    }

    #[Test]
    public function a_refund_reversed_then_recorded_again_leaves_the_right_sums(): void
    {
        $reversed = [];
        Event::listen(OrderRefundReversed::class, static function (OrderRefundReversed $event) use (&$reversed): void {
            $reversed[] = $event;
        });
        $order = $this->paidWithCertificate();
        $tender = $this->tender($order);
        $item = $this->item($order);

        // The manager typed 49.99 and the whole certificate where 20.00 and half of it went back.
        $wrong = $this->orders()->refund($order, new RefundRequest(4999, [$tender->id => 1000], [$item->id => 1], 'BANK-1', 'Broken', 'api'));

        $preview = $this->orders()->reverseRefund($order, (int) $wrong->refundId, 'Wrong amount', 'api', dryRun: true);
        $this->assertNull($preview->refusal);
        $this->assertTrue($preview->dryRun);
        $this->assertSame([4999, [$tender->id => 1000], [$item->id => 1]], [$preview->amount, $preview->tenders, $preview->items]);
        $this->assertSame([0, 8998, [$tender->id => 1000], [$item->id => 2]], [$preview->refundedTotal, $preview->moneyLeft, $preview->tendersLeft, $preview->itemsLeft]);
        $this->assertNull($preview->milestoneRemoved);
        // A dry run writes nothing.
        $this->assertSame(4999, $order->refresh()->refunded_total);
        $this->assertNull(OrderRefund::query()->whereKey($wrong->refundId)->sole()->reversed_at);
        $this->assertSame(0, $this->events($order, StatusChange::REFUND_REVERSED));

        $done = $this->orders()->reverseRefund($order, (int) $wrong->refundId, '  Wrong amount  ', 'api');

        $this->assertFalse($done->dryRun);
        $this->assertSame(0, $order->refunded_total);
        $this->assertSame([0, 0], [$this->tender($order)->refunded_amount, $this->item($order)->refunded_qty]);
        // The row stays, its sums as they were, with when, from where and why it was reversed.
        $row = OrderRefund::query()->whereKey($wrong->refundId)->sole();
        $this->assertSame([4999, 'BANK-1', 'Broken', 'api', 'Wrong amount'], [$row->amount, $row->reference, $row->reason, $row->reversed_source, $row->reversal_reason]);
        $this->assertNotNull($row->reversed_at);
        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::REFUND_REVERSED)->sole();
        $this->assertSame("refund#{$wrong->refundId}: Wrong amount", $line->note);

        $this->assertCount(1, $reversed);
        $this->assertSame([4999, 'Wrong amount', false, 0], [$reversed[0]->refund->amount, $reversed[0]->reason, $reversed[0]->milestoneRemoved, $reversed[0]->order->refundedTotal]);

        // The right one, recorded as any refund, against what is left after the reversal.
        $right = $this->orders()->refund($order, new RefundRequest(2000, [$tender->id => 500], [$item->id => 1], 'BANK-2', 'Broken', 'api'));
        $this->assertSame([8998, [$tender->id => 1000], [$item->id => 2]], [$right->moneyLeft, $right->tendersLeft, $right->itemsLeft]);
        $this->assertSame(2000, $order->refresh()->refunded_total);
        $this->assertSame([500, 1], [$this->tender($order)->refunded_amount, $this->item($order)->refunded_qty]);
    }

    #[Test]
    public function reversing_a_full_refund_takes_the_milestone_off_and_a_new_full_one_sets_it_again(): void
    {
        $full = [];
        Event::listen(OrderRefunded::class, static function (OrderRefunded $event) use (&$full): void {
            $full[] = $event->full;
        });
        $order = $this->paidWithCertificate();
        $tender = $this->tender($order);
        $item = $this->item($order);

        $first = $this->orders()->refund($order, new RefundRequest(8998, [$tender->id => 1000], [$item->id => 2], source: 'api'));
        $this->assertNotNull($order->refunded_at);

        $preview = $this->orders()->reverseRefund($order, (int) $first->refundId, 'Typed on the wrong order', 'api', dryRun: true);
        $this->assertSame(Milestone::Refunded, $preview->milestoneRemoved);
        $this->assertNotNull($order->refresh()->refunded_at);

        $this->orders()->reverseRefund($order, (int) $first->refundId, 'Typed on the wrong order', 'api');

        $this->assertNull($order->refunded_at);
        $this->assertSame(0, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'refunded')->count());
        $removed = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::MILESTONE_REMOVED)->sole();
        $this->assertSame('refunded', $removed->note);
        // Only «refunded» comes off; «paid» stays.
        $this->assertNotNull($order->paid_at);

        $again = $this->orders()->refund($order, new RefundRequest(8998, [$tender->id => 1000], source: 'api'));
        $this->assertSame(Milestone::Refunded, $again->milestone);
        $this->assertNotNull($order->refresh()->refunded_at);
        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'refunded')->count());
        $this->assertSame([true, true], $full);
    }

    #[Test]
    public function units_alone_reversed_keep_the_milestone_and_the_cancellation_s_tenders_stay(): void
    {
        $order = $this->paidWithCertificate();
        $tender = $this->tender($order);
        $item = $this->item($order);

        // Cancelled: the certificate goes back with the cancellation, not with a record.
        $this->orders()->cancel($order, 'api');
        $money = $this->orders()->refund($order, new RefundRequest(8998, source: 'api'));
        $units = $this->orders()->refund($order, new RefundRequest(0, items: [$item->id => 2], source: 'api'));
        $this->assertNotNull($order->refunded_at);

        $answer = $this->orders()->reverseRefund($order, (int) $units->refundId, 'Never came back', 'api');
        $this->assertNull($answer->milestoneRemoved);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame(0, $this->item($order)->refunded_qty);

        $answer = $this->orders()->reverseRefund($order, (int) $money->refundId, 'The bank sent it back', 'api');
        $this->assertSame(Milestone::Refunded, $answer->milestoneRemoved);
        $this->assertSame([8998, [$tender->id => 0]], [$answer->moneyLeft, $answer->tendersLeft]);
        $this->assertSame(1000, $this->tender($order)->refunded_amount);
        $this->assertNull($order->refunded_at);
        $this->assertNotNull($order->cancelled_at);
    }

    #[Test]
    public function another_order_s_record_and_one_reversed_already_are_refused_and_write_nothing(): void
    {
        $order = $this->paidWithCertificate();
        $other = $this->place($this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 1]]));
        $this->orders()->mark($other, Milestone::Paid, 'api');
        $theirs = $this->orders()->refund($other, new RefundRequest(1000, source: 'api'));
        $mine = $this->orders()->refund($order, new RefundRequest(1000, source: 'api'));

        $dry = $this->orders()->reverseRefund($order, (int) $theirs->refundId, 'Mistake', 'api', dryRun: true);
        $this->assertSame(RefundReversal::FOREIGN, $dry->refusal);
        $this->expectRefusal(RefundReversal::FOREIGN, fn () => $this->orders()->reverseRefund($order, (int) $theirs->refundId, 'Mistake', 'api'));
        $this->expectRefusal(RefundReversal::FOREIGN, fn () => $this->orders()->reverseRefund($order, 999999, 'Mistake', 'api'));
        $this->assertSame([1000, 1000], [$order->refresh()->refunded_total, $other->refresh()->refunded_total]);
        $this->assertNull(OrderRefund::query()->whereKey($theirs->refundId)->sole()->reversed_at);

        $this->orders()->reverseRefund($order, (int) $mine->refundId, 'Mistake', 'api');
        $writes = DB::table('order_status_changes')->count();
        $this->assertSame(RefundReversal::REVERSED, $this->orders()->reverseRefund($order, (int) $mine->refundId, 'Again', 'api', dryRun: true)->refusal);
        $this->expectRefusal(RefundReversal::REVERSED, fn () => $this->orders()->reverseRefund($order, (int) $mine->refundId, 'Again', 'api'));
        $this->assertSame(0, $order->refresh()->refunded_total);
        $this->assertSame($writes, DB::table('order_status_changes')->count());
        $this->assertSame('Mistake', OrderRefund::query()->whereKey($mine->refundId)->sole()->reversal_reason);
    }

    #[Test]
    public function the_panel_and_an_agent_give_a_reason_and_the_others_need_not(): void
    {
        $order = $this->paidWithCertificate();
        $one = $this->orders()->refund($order, new RefundRequest(1000, source: 'api'));
        $two = $this->orders()->refund($order, new RefundRequest(1000, source: 'api'));

        foreach (['panel', 'mcp'] as $source) {
            try {
                $this->orders()->reverseRefund($order, (int) $one->refundId, '   ', $source);
                $this->fail("{$source} reversed without a reason.");
            } catch (ValidationException $invalid) {
                $this->assertArrayHasKey('reason', $invalid->errors());
            }
        }

        $this->assertSame(2000, $order->refresh()->refunded_total);

        $this->orders()->reverseRefund($order, (int) $two->refundId, null, 'api');
        $this->assertSame("refund#{$two->refundId}", StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::REFUND_REVERSED)->sole()->note);
        $this->assertSame(1000, $order->refunded_total);
    }

    private function events(Order $order, string $kind): int
    {
        return StatusChange::query()->where('order_id', $order->id)->where('kind', $kind)->count();
    }

    private function expectRefusal(string $code, Closure $reverse): void
    {
        try {
            $reverse();
            $this->fail("The reversal was not refused with «{$code}».");
        } catch (RefundReversalRefused $refusal) {
            $this->assertSame($code, $refusal->reason);
        }
    }
}
