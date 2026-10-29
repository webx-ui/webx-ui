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
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderRefunded;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\MilestoneMark;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderRefund;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Orders;
use WebxUi\Orders\RefundRefused;
use WebxUi\Orders\RefundRequest;
use WebxUi\Orders\RefundResult;
use WebxUi\Orders\Tests\Fakes\FakeSources;

/** Cancelling with its reason (§9.1) and money given back outside the site (§9.2–§9.4). */
final class RefundTest extends PlaceTestCase
{
    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    /** @param  list<Tender>  $tenders */
    private function withTenders(array $tenders): void
    {
        $this->app->make(TenderSources::class)->register(new FakeSources(static fn (): array => $tenders));
    }

    /** Two units of «Item A» (2 × 49.99), paid, with a certificate of 10.00 counted against it. */
    private function paidWithCertificate(): Order
    {
        $this->withTenders([new Tender('wallet', 'Certificate', 1000, ['batches' => [3]])]);
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
    public function a_partial_then_a_full_refund_sets_the_milestone_once(): void
    {
        $events = [];
        Event::listen(OrderRefunded::class, static function (OrderRefunded $event) use (&$events): void {
            $events[] = $event;
        });
        $order = $this->paidWithCertificate();
        $tender = $this->tender($order);
        $item = $this->item($order);
        $this->assertSame(8998, $order->payable);

        $part = $this->orders()->refund($order, new RefundRequest(4999, [$tender->id => 400], [$item->id => 1], 'BANK-1', 'One came broken', 'api'));

        $this->assertFalse($part->full);
        $this->assertNull($part->milestone);
        $this->assertSame([8998, [$tender->id => 1000], [$item->id => 2]], [$part->moneyLeft, $part->tendersLeft, $part->itemsLeft]);
        $this->assertSame(4999, $order->refunded_total);
        $this->assertNull($order->refunded_at);
        // The snapshot stays; the refunds lie beside it.
        $this->assertSame([9998, 8998, 9998], [$order->total, $order->payable, $this->item($order)->total]);
        $this->assertSame([400, 1], [$this->tender($order)->refunded_amount, $this->item($order)->refunded_qty]);

        $row = OrderRefund::query()->whereKey($part->refundId)->sole();
        $this->assertSame(
            [4999, [['tender_id' => $tender->id, 'amount' => 400]], [['item_id' => $item->id, 'qty' => 1]], 'manual', 'BANK-1', 'One came broken', 'api', null],
            [$row->amount, $row->tenders, $row->items, $row->method, $row->reference, $row->reason, $row->source, $row->admin_id],
        );

        // The rest: what the dialog shows as left is what makes it full.
        $preview = $this->orders()->refund($order, new RefundRequest(3999, [$tender->id => 600]), dryRun: true);
        $this->assertTrue($preview->full);
        $this->assertSame(Milestone::Refunded, $preview->milestone);
        $this->assertSame([3999, [$tender->id => 600], [$item->id => 1]], [$preview->moneyLeft, $preview->tendersLeft, $preview->itemsLeft]);
        $this->assertSame(1, OrderRefund::query()->count());

        $rest = $this->orders()->refund($order, new RefundRequest(3999, [$tender->id => 600], source: 'api'));

        $this->assertTrue($rest->full);
        $this->assertSame(Milestone::Refunded, $rest->milestone);
        $this->assertSame(8998, $order->refunded_total);
        $this->assertNotNull($order->refunded_at);

        // The last unit back on its own: no money and no tender — the milestone is not set again.
        $units = $this->orders()->refund($order, new RefundRequest(0, items: [$item->id => 1], source: 'api'));
        $this->assertFalse($units->full);
        $this->assertNull($units->milestone);

        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'refunded')->count());
        $this->assertSame([false, true, false], array_map(static fn (OrderRefunded $event): bool => $event->full, $events));
        $this->assertSame([4999, 3999, 0], array_map(static fn (OrderRefunded $event): int => $event->refund->amount, $events));
        $this->assertSame([[['tender_id' => $tender->id, 'amount' => 600]], 'manual'], [$events[1]->refund->tenders, $events[1]->refund->method]);
        $this->assertSame(8998, $events[1]->order->refundedTotal);
        $this->assertArrayHasKey('refunded', $events[1]->order->milestones);
        $this->assertSame(2, $events[2]->order->items[0]->refundedQty);

        // Nothing is left to give back, and a cancellation of it is silence.
        $this->expectRefusal(RefundResult::AMOUNT, 'amount', fn () => $this->orders()->refund($order, new RefundRequest(1, source: 'api')));
        $this->assertSame(MilestoneMark::REFUNDED, $this->orders()->cancel($order, 'api')->outcome);
        $this->assertNull($order->cancelled_at);
    }

    #[Test]
    public function a_paid_order_cancelled_with_tenders_is_refunded_once_its_money_is_back(): void
    {
        $cancelled = [];
        Event::listen(OrderCancelled::class, static function (OrderCancelled $event) use (&$cancelled): void {
            $cancelled[] = $event;
        });
        $order = $this->paidWithCertificate();
        $tender = $this->tender($order);

        $preview = $this->orders()->cancel($order, 'panel', 'The buyer asked', dryRun: true);
        $mark = $this->orders()->cancel($order, 'panel', 'The buyer asked');

        // The panel warns: paid for 89.98, the cancellation does not give it back.
        foreach ([$preview, $mark] as $answer) {
            $this->assertSame(MilestoneMark::MARKED, $answer->outcome);
            $this->assertTrue($answer->wasPaid);
            $this->assertSame(8998, $answer->moneyToRefund);
            $this->assertSame([[$tender->id, 1000]], array_map(static fn ($left): array => [$left->id, $left->amount], $answer->tendersReturned));
        }

        $this->assertTrue($preview->dryRun);
        $this->assertFalse($mark->dryRun);
        $this->assertSame('The buyer asked', $mark->note);
        $this->assertCount(1, $cancelled);
        $this->assertSame([true, 'The buyer asked', 1000], [$cancelled[0]->wasPaid, $cancelled[0]->reason, $cancelled[0]->tendersReturned[0]->amount]);
        $this->assertSame(1000, $this->tender($order)->refunded_amount);

        // Money back — all of it: the tenders went back with the cancellation.
        $left = $this->orders()->refund($order, new RefundRequest(0), dryRun: true);
        $this->assertSame([8998, [$tender->id => 0], RefundResult::EMPTY], [$left->moneyLeft, $left->tendersLeft, $left->refusal]);

        $refund = $this->orders()->refund($order, new RefundRequest(8998, reason: 'Cancelled after payment'));

        $this->assertTrue($refund->full);
        $this->assertNotNull($order->refunded_at);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(['placed', 'paid', 'cancelled', 'refunded'], OrderMilestone::query()->where('order_id', $order->id)->orderBy('id')->pluck('milestone')->map->value->all());
        // A second cancel is silence and gives nothing back.
        $again = $this->orders()->cancel($order, 'mcp', 'Again');
        $this->assertTrue($again->alreadyMarked());
        $this->assertSame([], $again->tendersReturned);
        $this->assertCount(1, $cancelled);
    }

    #[Test]
    public function a_tender_given_back_by_a_cancellation_is_not_given_back_again(): void
    {
        Event::fake([OrderRefunded::class]);
        $order = $this->paidWithCertificate();
        $tender = $this->tender($order);
        $this->orders()->cancel($order, 'api');

        $this->expectRefusal(RefundResult::TENDER, "tenders.{$tender->id}", fn () => $this->orders()->refund($order, new RefundRequest(0, [$tender->id => 1])));
        $this->expectRefusal(RefundResult::TENDER, "tenders.{$tender->id}", fn () => $this->orders()->refund($order, new RefundRequest(100, [$tender->id => 1000])));

        $this->assertSame(1000, $this->tender($order)->refunded_amount);
        $this->assertSame(0, OrderRefund::query()->count());
        Event::assertNotDispatched(OrderRefunded::class);
    }

    #[Test]
    public function the_money_the_tenders_and_the_units_are_kept_to_what_is_left(): void
    {
        Event::fake([OrderRefunded::class]);
        $order = $this->paidWithCertificate();
        $tender = $this->tender($order);
        $item = $this->item($order);
        $this->orders()->refund($order, new RefundRequest(1000, [$tender->id => 300], [$item->id => 1]));
        $other = $this->place($this->cart());

        $cases = [
            [new RefundRequest(7999), RefundResult::AMOUNT, 'amount'],
            [new RefundRequest(-1), RefundResult::AMOUNT, 'amount'],
            [new RefundRequest(0, [$tender->id => 701]), RefundResult::TENDER, "tenders.{$tender->id}"],
            [new RefundRequest(0, [$tender->id => -5]), RefundResult::TENDER, "tenders.{$tender->id}"],
            [new RefundRequest(0, [$this->tender($order)->id + 99 => 1]), RefundResult::TENDER, 'tenders.'.($tender->id + 99)],
            [new RefundRequest(0, items: [$item->id => 2]), RefundResult::QTY, "items.{$item->id}"],
            [new RefundRequest(0, items: [$this->item($other)->id => 1]), RefundResult::QTY, "items.{$this->item($other)->id}"],
            [new RefundRequest(0, [$tender->id => 0], [$item->id => 0]), RefundResult::EMPTY, null],
        ];

        DB::enableQueryLog();

        foreach ($cases as [$request, $code, $field]) {
            $dry = $this->orders()->refund($order, $request, dryRun: true);
            $this->assertSame([$code, $field, true], [$dry->refusal, $dry->field, $dry->dryRun]);
            $this->expectRefusal($code, $field, fn () => $this->orders()->refund($order, $request));
        }

        $writes = array_filter(DB::getQueryLog(), static fn (array $query): bool => preg_match('/^\s*(insert|update|delete)/i', $query['query']) === 1);
        $this->assertSame([], array_values($writes));

        // Exactly what is left is allowed.
        $all = $this->orders()->refund($order, new RefundRequest(7998, [$tender->id => 700], [$item->id => 1]));
        $this->assertTrue($all->full);
        $this->assertSame([8998, 1000, 2], [$order->refunded_total, $this->tender($order)->refunded_amount, $this->item($order)->refunded_qty]);
        Event::assertDispatchedTimes(OrderRefunded::class, 2);
    }

    #[Test]
    public function an_order_paid_by_tenders_alone_and_cancelled_stays_cancelled(): void
    {
        $this->withTenders([new Tender('wallet', 'Certificate', 4999)]);
        $order = $this->place($this->cart());
        $this->assertSame(0, $order->payable);
        $this->orders()->cancel($order, 'api');

        $this->expectRefusal(RefundResult::EMPTY, null, fn () => $this->orders()->refund($order, new RefundRequest(0)));
        $units = $this->orders()->refund($order, new RefundRequest(0, items: [$this->item($order)->id => 1]));

        $this->assertFalse($units->full);
        $this->assertNull($order->refunded_at);
        $this->assertNotNull($order->cancelled_at);
    }

    #[Test]
    public function the_panel_and_an_agent_cancel_with_a_reason_and_a_dry_run_writes_nothing(): void
    {
        Event::fake([OrderCancelled::class]);
        $order = $this->place($this->cart());

        foreach (['panel', 'mcp'] as $source) {
            try {
                $this->orders()->cancel($order, $source, '   ');
                $this->fail("{$source} cancelled without a reason.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('reason', $e->errors());
            }
        }

        DB::enableQueryLog();
        $preview = $this->orders()->cancel($order, 'panel', 'Out of stock', dryRun: true);
        $writes = array_filter(DB::getQueryLog(), static fn (array $query): bool => preg_match('/^\s*(insert|update|delete)/i', $query['query']) === 1);

        $this->assertSame([], array_values($writes));
        $this->assertSame([MilestoneMark::MARKED, false, 0, []], [$preview->outcome, $preview->wasPaid, $preview->moneyToRefund, $preview->tendersReturned]);
        $this->assertNull(Order::query()->findOrFail($order->id)->cancelled_at);
        Event::assertNotDispatched(OrderCancelled::class);

        // An integration or a command is known by its source; a reason is its own affair.
        $this->assertTrue($this->orders()->cancel($order, 'api')->marked());
        $this->assertNotNull($order->cancelled_at);
        $dryAgain = $this->orders()->cancel($order, 'panel', 'Twice', dryRun: true);
        $this->assertSame([MilestoneMark::ALREADY_MARKED, 'api'], [$dryAgain->outcome, $dryAgain->source]);
        Event::assertDispatchedTimes(OrderCancelled::class, 1);
    }

    private function expectRefusal(string $code, ?string $field, Closure $refund): void
    {
        try {
            $refund();
            $this->fail("The refund was not refused with «{$code}».");
        } catch (RefundRefused $refusal) {
            $this->assertSame([$code, $field], [$refusal->reason, $refusal->result->field]);
        }
    }
}
