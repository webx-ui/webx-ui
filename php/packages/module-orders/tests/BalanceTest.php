<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Cart\Totals\TenderSources;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderPaymentReceived;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderCancelledAlready;
use WebxUi\Orders\OrderPaidAlready;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Panel\OrdersSummary;
use WebxUi\Orders\PaymentRecord;
use WebxUi\Orders\PaymentRefused;
use WebxUi\Orders\RefundRequest;
use WebxUi\Orders\RefundResult;
use WebxUi\Orders\Tests\Fakes\FakeSources;

/**
 * The balance of an order (spec §36): `paid_total` and the stored `balance`, written by every
 * method that changes one of the terms, in the same update — and after each of them the column
 * is the formula `due − over` (decision of 30.10.2026: a refund is never a debt). A top-up is
 * `recordPayment()`, never a second «paid»; the limit of a refund is what came in when more came
 * than the order costs.
 */
final class BalanceTest extends PlaceTestCase
{
    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    /** Two units of «Item A»: 99.98 to pay. */
    private function order(): Order
    {
        return $this->place($this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]));
    }

    /**
     * The row as it is in the database, its balance the formula of §36.1 counted here apart.
     *
     * @return array{paid: int, balance: int, due: int, over: int}
     */
    private function balanceOf(Order $order): array
    {
        $row = Order::query()->findOrFail($order->id);
        $owed = $row->cancelled_at !== null ? 0 : $row->payable;
        $due = $row->cancelled_at !== null ? 0 : max(0, $row->payable - $row->paid_total);
        $over = max(0, $row->paid_total - $row->refunded_total - $owed);

        $this->assertSame($due - $over, $row->balance, 'The column is the formula.');
        $this->assertSame(Balance::stored($row), $row->balance);

        $balance = $this->orders()->balance($row);

        return ['paid' => $balance->paid, 'balance' => $row->balance, 'due' => $balance->due, 'over' => $balance->over];
    }

    #[Test]
    public function every_method_writes_the_balance_it_changes(): void
    {
        $order = $this->order();
        // place(): nothing came in yet, the whole sum is owed.
        $this->assertSame(['paid' => 0, 'balance' => 9998, 'due' => 9998, 'over' => 0], $this->balanceOf($order));

        // mark(paid) without an amount: paid whole, what it owed.
        $this->orders()->mark($order, Milestone::Paid, 'panel');
        $this->assertSame(['paid' => 9998, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($order));

        // recordPayment(): a top-up, more than is owed — the balance shows money to give back.
        $this->orders()->recordPayment($order, 500, 'panel', 'BANK-7');
        $this->assertSame(['paid' => 10498, 'balance' => -500, 'due' => 0, 'over' => 500], $this->balanceOf($order));

        // refund(): the overpayment goes back.
        $this->orders()->refund($order, new RefundRequest(500, source: 'api'));
        $this->assertSame(['paid' => 10498, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($order));

        // reverseRefund(): it counts no more.
        $refund = (int) DB::table('order_refunds')->where('order_id', $order->id)->value('id');
        $this->orders()->reverseRefund($order, $refund, 'Typed by mistake', 'api');
        $this->assertSame(['paid' => 10498, 'balance' => -500, 'due' => 0, 'over' => 500], $this->balanceOf($order));

        // cancel(): it owes nothing now, so everything that came in is the shop's to give back.
        $this->orders()->cancel($order, 'api', 'The buyer changed their mind');
        $this->assertSame(['paid' => 10498, 'balance' => -10498, 'due' => 0, 'over' => 10498], $this->balanceOf($order));

        // The refund of all of it closes the account and gives the order back whole.
        $this->orders()->refund($order, new RefundRequest(10498, source: 'api'));
        $this->assertSame(['paid' => 10498, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($order));
        $this->assertNotNull($order->refresh()->refunded_at);
    }

    #[Test]
    public function a_gateway_s_amount_is_counted_as_it_came(): void
    {
        $order = $this->order();

        $this->orders()->mark($order, Milestone::Paid, 'gateway', amount: 3000);

        $this->assertSame(['paid' => 3000, 'balance' => 6998, 'due' => 6998, 'over' => 0], $this->balanceOf($order));
        $this->assertSame('topup', $this->orders()->balance($order->refresh())->kind());

        // A second «paid» is silence: no money is counted twice.
        $this->orders()->mark($order, Milestone::Paid, 'gateway', amount: 3000);
        $this->assertSame(3000, $order->refresh()->paid_total);
    }

    #[Test]
    public function a_status_that_sets_paid_pays_the_order_whole(): void
    {
        $paidStatus = OrderStatus::query()->orderBy('id')->firstOrFail()->replicate();
        $paidStatus->fill(['sets_milestone' => Milestone::Paid->value, 'position' => 99])->save();
        $order = $this->order();

        $this->orders()->moveTo($order, $paidStatus, 'api');

        $this->assertSame(['paid' => 9998, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($order));
    }

    #[Test]
    public function a_cancelled_order_never_paid_owes_nothing(): void
    {
        $order = $this->order();

        $mark = $this->orders()->cancel($order, 'api', 'Spam');

        $this->assertSame(['paid' => 0, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($order));
        $this->assertSame(0, $mark->moneyToRefund);
    }

    #[Test]
    public function a_top_up_is_a_history_line_and_its_own_event_not_a_second_paid(): void
    {
        $order = $this->order();
        $this->orders()->mark($order, Milestone::Paid, 'panel');
        $paid = [];
        $received = [];
        Event::listen(OrderPaid::class, static function (OrderPaid $event) use (&$paid): void {
            $paid[] = $event;
        });
        Event::listen(OrderPaymentReceived::class, static function (OrderPaymentReceived $event) use (&$received): void {
            $received[] = $event;
        });

        $record = $this->orders()->recordPayment($order, 1500, 'panel', 'BANK-77', note: 'At the desk');

        $this->assertNull($record->refusal);
        $this->assertFalse($record->dryRun);
        $this->assertSame([0, -1500], [$record->before->due, $record->after->due - $record->after->over]);
        $this->assertSame([], $paid, 'The milestone is set once (decision 24).');
        $this->assertCount(1, $received);
        $this->assertSame([1500, 'BANK-77', 1500, 11498, -1500], [$received[0]->amount, $received[0]->reference, $received[0]->balance->over, $received[0]->order->paidTotal, $received[0]->order->balance]);
        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Paid->value)->count());

        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::PAYMENT)->sole();
        $this->assertSame('1500 [BANK-77]: At the desk', $line->note);
        $this->assertSame('panel', $line->source);
    }

    #[Test]
    public function a_dry_run_answers_the_balance_after_it_and_writes_nothing(): void
    {
        $order = $this->order();
        $this->orders()->mark($order, Milestone::Paid, 'panel', amount: 9000);
        $events = [];
        Event::listen(OrderPaymentReceived::class, static function () use (&$events): void {
            $events[] = true;
        });

        $record = $this->orders()->recordPayment($order, 998, 'mcp', dryRun: true);

        $this->assertTrue($record->dryRun);
        $this->assertSame([998, 0, 0], [$record->before->due, $record->after->due, $record->after->over]);
        $this->assertSame(9000, $order->refresh()->paid_total);
        $this->assertSame(0, StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::PAYMENT)->count());
        $this->assertSame([], $events);
    }

    #[Test]
    public function a_top_up_of_an_order_not_paid_or_cancelled_is_refused(): void
    {
        $order = $this->order();

        // The first payment is mark(paid): its milestone, its letters.
        $this->assertSame(PaymentRecord::NOT_PAID, $this->orders()->recordPayment($order, 100, 'mcp', dryRun: true)->refusal);

        try {
            $this->orders()->recordPayment($order, 100, 'panel');
            $this->fail('An order not paid takes no top-up.');
        } catch (PaymentRefused $refused) {
            $this->assertSame(PaymentRecord::NOT_PAID, $refused->reason);
        }

        $this->orders()->mark($order, Milestone::Paid, 'panel');
        $this->orders()->cancel($order, 'api', 'Out of stock');

        try {
            $this->orders()->recordPayment($order, 100, 'panel');
            $this->fail('Money to a cancelled order is a refund to make.');
        } catch (PaymentRefused $refused) {
            $this->assertSame(PaymentRecord::CANCELLED, $refused->reason);
        }

        $this->assertSame(9998, $order->refresh()->paid_total);
        $this->assertSame(0, StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::PAYMENT)->count());

        $this->expectException(ValidationException::class);
        $this->orders()->recordPayment($order, 0, 'panel', dryRun: true);
    }

    #[Test]
    public function an_overpayment_is_refunded_beyond_the_order_s_sum(): void
    {
        $order = $this->order();
        $this->orders()->mark($order, Milestone::Paid, 'panel');

        // An edit (§37) lowers what the order costs — written straight here, as the edit will:
        // payable and the balance in the same update.
        $row = Order::query()->findOrFail($order->id);
        $row->payable = 5000;
        $row->balance = Balance::stored($row);
        $row->save();
        $this->assertSame(['paid' => 9998, 'balance' => -4998, 'due' => 0, 'over' => 4998], $this->balanceOf($order));

        // The limit is what came in, not the new payable (§36.2).
        $tooMuch = $this->orders()->refund($order, new RefundRequest(9999, source: 'api'), dryRun: true);
        $this->assertSame([RefundResult::AMOUNT, 9998], [$tooMuch->refusal, $tooMuch->moneyLeft]);

        $over = $this->orders()->refund($order, new RefundRequest(4998, source: 'api'));
        $this->assertFalse($over->full, 'Giving the overpayment back leaves the order paid.');
        $this->assertSame(['paid' => 9998, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($order));
        $this->assertNull($order->refresh()->refunded_at);

        $rest = $this->orders()->refund($order, new RefundRequest(5000, source: 'api'));
        $this->assertTrue($rest->full, 'refunded_total = max(payable, paid_total): given back whole (§36.2).');
        $this->assertNotNull($order->refresh()->refunded_at);
    }

    /**
     * The cases of the decision of 30.10.2026, one by one: a refund gives back money that came in
     * and never makes the order owe; only `paid_total` against `payable` does.
     */
    #[Test]
    public function a_refund_never_makes_the_order_owe(): void
    {
        $awaiting = fn (): array => Order::query()->where('balance', '>', 0)->whereNotNull('paid_at')->pluck('id')->all();

        // Not paid: the whole sum is due — the ordinary «to pay», not a top-up.
        $unpaid = $this->order();
        $this->assertSame(['paid' => 0, 'balance' => 9998, 'due' => 9998, 'over' => 0], $this->balanceOf($unpaid));
        $this->assertSame('unpaid', $this->orders()->balance($unpaid->refresh())->kind());

        // Paid: settled.
        $paid = $this->order();
        $this->orders()->mark($paid, Milestone::Paid, 'panel');
        $this->assertSame(['paid' => 9998, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($paid));

        // Paid, one unit given back without an edit: still settled, not «awaiting a top-up».
        $this->orders()->refund($paid, new RefundRequest(1000, source: 'api'));
        $this->assertSame(['paid' => 9998, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($paid));
        $this->assertNull($this->orders()->balance($paid->refresh())->kind());
        $this->assertSame([], $awaiting());
        $this->assertSame(0, $this->app->make(OrdersSummary::class)->describe('en')['awaiting_topup']);

        // Paid 5 more than it costs: 5 over; given back — settled.
        $over = $this->order();
        $this->orders()->mark($over, Milestone::Paid, 'panel');
        $this->orders()->recordPayment($over, 500, 'panel');
        $this->assertSame(['paid' => 10498, 'balance' => -500, 'due' => 0, 'over' => 500], $this->balanceOf($over));
        $this->orders()->refund($over, new RefundRequest(500, source: 'api'));
        $this->assertSame(['paid' => 10498, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($over));

        // Cancelled after it was paid and partly refunded: over is what the cancel dialog gives back.
        $cancelled = $this->order();
        $this->orders()->mark($cancelled, Milestone::Paid, 'panel');
        $this->orders()->refund($cancelled, new RefundRequest(1000, source: 'api'));
        $mark = $this->orders()->cancel($cancelled, 'api', 'Out of stock');
        $this->assertSame(['paid' => 9998, 'balance' => -8998, 'due' => 0, 'over' => 8998], $this->balanceOf($cancelled));
        $this->assertSame(8998, $mark->moneyToRefund);

        // An edit (§37) up and down — written straight here, as the edit will: payable and the
        // balance in the same update. Up: the difference is due; down: the difference is over.
        foreach ([[12000, 2002, 0], [7000, 0, 2998]] as [$payable, $due, $overBy]) {
            $row = Order::query()->findOrFail($paid->id);
            $row->refunded_total = 0;
            $row->payable = $payable;
            $row->balance = Balance::stored($row);
            $row->save();
            $this->assertSame(['paid' => 9998, 'balance' => $due - $overBy, 'due' => $due, 'over' => $overBy], $this->balanceOf($paid));
        }
        $this->assertSame([], $awaiting(), 'Lowered: nothing to pay.');

        $row = Order::query()->findOrFail($paid->id);
        $row->payable = 12000;
        $row->balance = Balance::stored($row);
        $row->save();
        $this->assertSame([$paid->id], $awaiting());
        $this->assertSame('topup', $this->orders()->balance($row)->kind());
    }

    #[Test]
    public function an_order_without_edits_counts_as_before(): void
    {
        $this->app->make(TenderSources::class)->register(new FakeSources(static fn (): array => [new Tender('wallet', 'Certificate', 1000, ['batches' => [3]])]));
        $order = $this->order();
        $this->orders()->mark($order, Milestone::Paid, 'panel');

        // paid_total is payable for a paid order that was never edited: the limit, the full
        // refund, the cancellation and the revenue read the same as by payable.
        $this->assertSame($order->refresh()->payable, $order->paid_total);
        $plan = $this->orders()->refund($order, new RefundRequest(1000, source: 'api'), dryRun: true);
        $this->assertSame($order->payable - $order->refunded_total, $plan->moneyLeft);

        $this->orders()->refund($order, new RefundRequest(1000, source: 'api'));
        $mark = $this->orders()->cancel($order, 'api', 'Changed their mind', dryRun: true);
        $this->assertSame($order->refresh()->payable - 1000, $mark->moneyToRefund);

        $summary = $this->app->make(OrdersSummary::class)->describe('en');
        $today = $summary['periods'][0]['by_currency'][0];
        $this->assertSame([1, $order->payable - 1000], [$today['paid'], $today['revenue']]);
        $this->assertSame([0, []], [$summary['awaiting_topup'], $summary['topup_by_currency']]);

        $full = $this->orders()->refund($order, new RefundRequest($order->payable - 1000, [(int) DB::table('order_tenders')->where('order_id', $order->id)->value('id') => 1000], source: 'api'), dryRun: true);
        $this->assertTrue($full->full);
    }

    #[Test]
    public function the_summary_counts_the_top_ups_and_the_money_that_came_in(): void
    {
        $owing = $this->order();
        $this->orders()->mark($owing, Milestone::Paid, 'gateway', amount: 8000);
        $over = $this->order();
        $this->orders()->mark($over, Milestone::Paid, 'panel');
        $this->orders()->recordPayment($over, 2000, 'panel');
        $this->order();

        $summary = $this->app->make(OrdersSummary::class)->describe('en');

        $this->assertSame(1, $summary['awaiting_topup']);
        $this->assertSame([['currency' => 'EUR', 'orders' => 1, 'amount' => 1998]], array_map(
            static fn (array $row): array => array_intersect_key($row, array_flip(['currency', 'orders', 'amount'])),
            $summary['topup_by_currency'],
        ));
        $this->assertSame(8000 + 9998 + 2000, $summary['periods'][0]['by_currency'][0]['revenue']);
    }

    #[Test]
    public function await_payment_is_the_pair_of_stop_awaiting_it(): void
    {
        $order = $this->order();
        $this->assertFalse($order->awaiting_payment);

        $this->orders()->awaitPayment($order, 'payments');
        $this->orders()->awaitPayment($order, 'payments');

        $this->assertTrue($order->refresh()->awaiting_payment);
        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::AwaitingPayment->value)->count());
        $this->assertSame(1, StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::PAYMENT_AWAITING)->count());

        // Stopped and started again: the milestone stays the first one, the lines are two.
        $this->orders()->stopAwaitingPayment($order, 'payments');
        $this->orders()->awaitPayment($order, 'payments');
        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::AwaitingPayment->value)->count());
        $this->assertSame(2, StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::PAYMENT_AWAITING)->count());

        $this->orders()->mark($order, Milestone::Paid, 'panel');

        try {
            $this->orders()->awaitPayment($order, 'payments');
            $this->fail('A paid order does not await payment.');
        } catch (OrderPaidAlready $refused) {
            $this->assertSame('paid', $refused->reason);
        }

        $cancelled = $this->order();
        $this->orders()->cancel($cancelled, 'api', 'Spam');
        $this->expectException(OrderCancelledAlready::class);
        $this->orders()->awaitPayment($cancelled, 'payments');
    }

    #[Test]
    public function the_migration_fills_the_balance_of_the_orders_there_are(): void
    {
        $paid = $this->order();
        $this->orders()->mark($paid, Milestone::Paid, 'panel');
        $unpaid = $this->order();
        $partly = $this->order();
        $this->orders()->mark($partly, Milestone::Paid, 'panel');
        $this->orders()->refund($partly, new RefundRequest(1000, source: 'api'));
        $returned = $this->order();
        $this->orders()->mark($returned, Milestone::Paid, 'panel');
        $this->orders()->cancel($returned, 'api', 'Out of stock');
        $this->orders()->refund($returned, new RefundRequest(9998, source: 'api'));

        $migration = require __DIR__.'/../database/migrations/2026_01_01_000003_add_balance_to_orders.php';
        $migration->down();
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('orders', 'balance'));
        $migration->up();

        $this->assertSame(['paid' => 9998, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($paid));
        $this->assertSame(['paid' => 0, 'balance' => 9998, 'due' => 9998, 'over' => 0], $this->balanceOf($unpaid));
        $this->assertSame(['paid' => 9998, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($partly));
        $this->assertSame(['paid' => 9998, 'balance' => 0, 'due' => 0, 'over' => 0], $this->balanceOf($returned));
    }
}
