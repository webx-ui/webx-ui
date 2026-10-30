<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Cart\Totals\CartContext;
use WebxUi\Cart\Totals\GeneratedLine;
use WebxUi\Cart\Totals\LineSources;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Events\OrderItemsChanged;
use WebxUi\Orders\Events\OrderItemsChanging;
use WebxUi\Orders\ItemsEdit;
use WebxUi\Orders\ItemsEditRefused;
use WebxUi\Orders\ItemsEditResult;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderRevision;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;
use WebxUi\Orders\RefundRequest;
use WebxUi\Orders\Tests\Fakes\FakeSources;

/**
 * Editing the lines of a placed order (spec §37–§39, decision 101 — variant A): the lines that
 * were keep their price and their discount in proportion, a line added comes at the price now
 * without the rules, the discounts of the order stay; a line is never deleted; the edit is a
 * row of `order_revisions`, a line of the history and two events; a dry run writes nothing.
 */
final class ItemsEditTest extends PlaceTestCase
{
    /** What the rule on «Item A» gives now; a test changes it after the order. */
    private int $discount = -500;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(AdjustmentSources::class)->register(new FakeSources(function (CartContext $cart): array {
            $adjustments = [new Adjustment('coupon#SPRING', 'Spring', -1000)];

            foreach ($cart->lines as $line) {
                if ($line->refId === 1 && ! $line->generated()) {
                    $adjustments[] = new Adjustment('rule#3', 'Two for less', $this->discount, $line->key);
                }
            }

            return $adjustments;
        }));
    }

    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    /**
     * «Item A» × 2 with the rule's −5.00, the bundle «Set B» with two «Part C», the coupon −10.00:
     * 2 × 49.99 + 150.00 + 2 × 7.00 = 263.98; −15.00 = 248.98.
     */
    private function order(): Order
    {
        return $this->place($this->cart([
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2],
            ['type' => 'test.product', 'ref' => 2, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 3, 'qty' => 2, 'parent' => 'set'],
        ]));
    }

    private function item(Order $order, int $ref, int $revision = 0): OrderItem
    {
        return OrderItem::query()->where('order_id', $order->id)->where('ref_id', $ref)->where('revision', $revision)->sole();
    }

    /** @param  array<string, mixed>  $with  named arguments of ItemsEdit over the defaults */
    private function edit(Order $order, array $with, bool $dryRun = false): ItemsEditResult
    {
        return $this->orders()->editItems($order, new ItemsEdit(...[
            'revision' => Order::query()->whereKey($order->id)->value('items_revision'),
            'reason' => 'The buyer called',
            ...$with,
        ]), $dryRun);
    }

    private function refusal(callable $edit): ItemsEditRefused
    {
        try {
            $edit();
        } catch (ItemsEditRefused $refused) {
            return $refused;
        }

        $this->fail('The edit was not refused.');
    }

    /** @return array<string, mixed> every table an edit writes, as it is now */
    private function tables(Order $order): array
    {
        return [
            'order' => Order::query()->findOrFail($order->id)->getAttributes(),
            'items' => DB::table('order_items')->orderBy('id')->get()->all(),
            'adjustments' => DB::table('order_adjustments')->orderBy('id')->get()->all(),
            'revisions' => DB::table('order_revisions')->count(),
            'history' => DB::table('order_status_changes')->count(),
            'carts' => DB::table('carts')->count(),
            'cart_items' => DB::table('cart_items')->count(),
        ];
    }

    #[Test]
    public function the_lines_placed_keep_their_snapshot_and_a_line_added_comes_at_today_s_price(): void
    {
        $order = $this->order();
        $itemA = $this->item($order, 1);
        $carts = DB::table('carts')->count();
        $changed = [];
        Event::listen(OrderItemsChanged::class, static function (OrderItemsChanged $event) use (&$changed): void {
            $changed[] = $event;
        });

        // Everything changes behind the order's back: the prices and the rule.
        $this->products->lines[1] = self::product('Item A', 5999);
        $this->products->lines[2] = self::product('Set B', 19000);
        $this->products->lines[3] = self::product('Part C', 900);
        $this->discount = -900;

        $result = $this->edit($order, [
            'add' => [['type' => 'test.product', 'ref' => 1]],
            'qty' => [$itemA->id => 3],
            'message' => 'We added the one you asked for.',
        ]);

        // «Item A» × 3 at 49.99, its discount −5.00 × 3 / 2 = −7.50; the new «Item A» at 59.99 —
        // another price, so a line of its own — and without the rule's −9.00.
        $itemA->refresh();
        $this->assertSame([3, 4999, -750, 14247, 0], [$itemA->qty, $itemA->price, $itemA->adjustments_total, $itemA->total, $itemA->revision]);
        $this->assertSame(-750, OrderAdjustment::query()->where('item_id', $itemA->id)->value('amount'));

        $added = $this->item($order, 1, revision: 1);
        $this->assertSame([1, 5999, 0, 5999, 3], [$added->qty, $added->price, $added->adjustments_total, $added->total, $added->position]);
        $this->assertSame(0, OrderAdjustment::query()->where('item_id', $added->id)->count());

        // The bundle and the coupon are as placed.
        $set = $this->item($order, 2);
        $part = $this->item($order, 3);
        $this->assertSame([1, 15000, 15000], [$set->qty, $set->price, $set->total]);
        $this->assertSame([2, 700, 1400], [$part->qty, $part->price, $part->total]);
        $this->assertSame(-1000, OrderAdjustment::query()->whereNull('item_id')->where('order_id', $order->id)->value('amount'));

        // 263.98 + 49.99 + 59.99 = 373.96; −17.50 = 356.46.
        $order->refresh();
        $after = ['subtotal' => 37396, 'adjustments_total' => -1750, 'total' => 35646, 'tenders_total' => 0, 'payable' => 35646, 'balance' => 35646];
        $this->assertSame($after, array_intersect_key($order->getAttributes(), $after));
        $this->assertSame([1, 1], [$order->items_revision, $result->revision]);
        $this->assertNotNull($order->items_changed_at);
        $this->assertSame($after, $result->after);
        $this->assertSame(24898, $result->before['payable']);

        // The edit's row: «before — after», in the order's words.
        $revision = OrderRevision::query()->where('order_id', $order->id)->sole();
        $this->assertSame(1, $revision->number);
        $this->assertSame([
            ['op' => 'qty', 'item_id' => $itemA->id, 'name' => 'Item A', 'options_label' => null, 'price' => 4999, 'qty_from' => 2, 'qty_to' => 3, 'total_from' => 9498, 'total_to' => 14247],
            ['op' => 'add', 'item_id' => $added->id, 'name' => 'Item A', 'options_label' => null, 'price' => 5999, 'qty_from' => 0, 'qty_to' => 1, 'total_from' => 0, 'total_to' => 5999],
        ], $revision->changes);
        $adjustment = (int) OrderAdjustment::query()->where('item_id', $itemA->id)->value('id');
        $this->assertSame([['adjustment_id' => $adjustment, 'label' => 'Two for less', 'amount_from' => -500, 'amount_to' => -750]], $revision->adjustments);
        $this->assertSame(['The buyer called', 'We added the one you asked for.', 'panel'], [$revision->reason, $revision->message, $revision->source]);
        $this->assertSame($after, $revision->after);

        // The coupon was counted from the old sum: the manager is told it stayed.
        $this->assertContains(ItemsEditResult::WARN_ADJUSTMENT_KEPT, $result->warningCodes());

        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::ITEMS)->sole();
        $this->assertSame('revision#1: The buyer called', $line->note);

        // The cart of the edit is gone; the event carries the edit and the balance after it.
        $this->assertSame($carts, DB::table('carts')->count());
        $this->assertCount(1, $changed);
        $this->assertSame(1, $changed[0]->revision->number);
        $this->assertSame(35646, $changed[0]->balance->due);
        $this->assertTrue($changed[0]->notify);
        $this->assertSame(1, $changed[0]->order->itemsRevision);
        $this->assertCount(4, $changed[0]->order->items);
    }

    #[Test]
    public function the_same_thing_at_the_same_price_is_more_of_its_line(): void
    {
        $order = $this->order();
        $itemA = $this->item($order, 1);

        $result = $this->edit($order, ['add' => [['type' => 'test.product', 'ref' => 1]]]);

        $this->assertSame(3, $itemA->refresh()->qty);
        $this->assertSame(3, OrderItem::query()->where('order_id', $order->id)->count());
        $this->assertSame([['op' => 'qty', 'item_id' => $itemA->id]], array_map(static fn (array $change): array => array_intersect_key($change, ['op' => 0, 'item_id' => 0]), $result->changes));
        $this->assertSame(-750, $itemA->adjustments_total);
    }

    #[Test]
    public function a_second_edit_of_the_revision_seen_is_stale(): void
    {
        $order = $this->order();
        $itemA = $this->item($order, 1);

        $this->edit($order, ['qty' => [$itemA->id => 3], 'revision' => 0]);

        $refused = $this->refusal(fn () => $this->edit($order, ['qty' => [$itemA->id => 1], 'revision' => 0]));
        $this->assertSame(ItemsEditResult::STALE, $refused->reason);
        $this->assertSame('revision', $refused->result->field);
        $this->assertSame(3, $itemA->refresh()->qty);

        // A dry run answers the same, in the answer.
        $this->assertSame(ItemsEditResult::STALE, $this->edit($order, ['qty' => [$itemA->id => 1], 'revision' => 0], dryRun: true)->refusal);

        // With the revision it is now, the edit goes.
        $this->assertSame(2, $this->edit($order, ['qty' => [$itemA->id => 1], 'revision' => 1])->revision);
    }

    #[Test]
    public function a_refusal_of_a_listener_rolls_the_edit_back_whole(): void
    {
        $order = $this->order();
        $itemA = $this->item($order, 1);
        $before = $this->tables($order);
        Event::listen(OrderItemsChanging::class, static fn () => throw new OrderRefused('stock.none', 'Nothing left in stock.'));

        try {
            $this->edit($order, ['add' => [['type' => 'test.product', 'ref' => 4]], 'qty' => [$itemA->id => 5]]);
            $this->fail('The listener did not refuse.');
        } catch (OrderRefused $refused) {
            $this->assertSame('stock.none', $refused->reason);
        }

        // Neither the lines, the money, the edit, the history — nor the cart of the edit.
        $this->assertEquals($before, $this->tables($order));
    }

    #[Test]
    public function a_dry_run_writes_nothing_and_answers_what_the_edit_does(): void
    {
        $order = $this->order();
        $itemA = $this->item($order, 1);
        $set = $this->item($order, 2);
        $before = $this->tables($order);
        $edit = ['add' => [['type' => 'test.product', 'ref' => 4, 'qty' => 2]], 'qty' => [$itemA->id => 1], 'remove' => [$set->id]];
        $events = 0;
        Event::listen([OrderItemsChanging::class, OrderItemsChanged::class], static function () use (&$events): void {
            $events++;
        });

        $dry = $this->edit($order, $edit, dryRun: true);

        $this->assertEquals($before, $this->tables($order));
        $this->assertSame(0, $events);
        $this->assertTrue($dry->dryRun);
        $this->assertNull($dry->revision);
        $this->assertNull($dry->refusal);

        $real = $this->edit($order, $edit);

        $this->assertSame($real->after, $dry->after);
        $this->assertEquals($real->balance, $dry->balance);
        $this->assertSame($real->warnings, $dry->warnings);
        $this->assertSame($real->adjustments, $dry->adjustments);
        // The line added has its id once it is written.
        $withoutIds = static fn (ItemsEditResult $result): array => array_map(static fn (array $change): array => [...$change, 'item_id' => $change['op'] === 'add' ? null : $change['item_id']], $result->changes);
        $this->assertSame($withoutIds($real), $withoutIds($dry));
        $this->assertSame(['qty', 'remove', 'add'], array_column($real->changes, 'op'));
        $this->assertNotNull($real->added()[0]['item_id']);
    }

    #[Test]
    public function a_bundle_changes_whole_and_its_parts_not_alone(): void
    {
        $order = $this->order();
        $set = $this->item($order, 2);
        $part = $this->item($order, 3);

        $refused = $this->refusal(fn () => $this->edit($order, ['qty' => [$part->id => 4]]));
        $this->assertSame([ItemsEditResult::CHILD, "qty.{$part->id}"], [$refused->reason, $refused->result->field]);

        $this->edit($order, ['qty' => [$set->id => 3]]);

        $this->assertSame([3, 45000], [$set->refresh()->qty, $set->total]);
        $this->assertSame([6, 4200], [$part->refresh()->qty, $part->total]);
        // 2 × 49.99 + 3 × 150.00 + 6 × 7.00 = 591.98; −15.00 = 576.98.
        $this->assertSame(57698, $order->refresh()->payable);

        // Taken out, the bundle goes whole — and stays a row: refunds and the history point at it.
        $this->edit($order, ['remove' => [$set->id]]);

        $this->assertSame(0, $set->refresh()->qty);
        $this->assertNotNull($set->removed_at);
        $this->assertSame(0, $part->refresh()->qty);
        $this->assertNotNull($part->removed_at);
        $this->assertSame(8498, $order->refresh()->payable);
        $this->assertSame([1], array_map(static fn ($item): int => $item->refId, OrderData::of($order)->items));

        // A line taken out is not edited again.
        $this->assertSame(ItemsEditResult::FOREIGN, $this->refusal(fn () => $this->edit($order, ['qty' => [$set->id => 1]]))->reason);
    }

    #[Test]
    public function a_rule_s_gift_may_go_but_not_be_counted_again(): void
    {
        $this->app->make(LineSources::class)->register(new FakeSources(static function (CartContext $cart): array {
            foreach ($cart->lines as $line) {
                if ($line->refId === 1 && ! $line->generated() && is_int($line->key)) {
                    return [new GeneratedLine('rule#7', 'test.product', 4, parentId: $line->key, price: 1, label: 'Gift')];
                }
            }

            return [];
        }));
        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]));
        $itemA = $this->item($order, 1);
        $gift = $this->item($order, 4);
        $this->assertSame('rule#7', $gift->source);

        // The gift is a child of its line, not a part of a bundle: it does not follow the qty…
        $this->edit($order, ['qty' => [$itemA->id => 4]]);
        $this->assertSame(1, $gift->refresh()->qty);
        // …and a line added at the price of a line with a gift is still more of it.
        $this->edit($order, ['add' => [['type' => 'test.product', 'ref' => 1]]]);
        $this->assertSame(5, $itemA->refresh()->qty);

        $this->assertSame(ItemsEditResult::CHILD, $this->refusal(fn () => $this->edit($order, ['qty' => [$gift->id => 2]]))->reason);
    }

    #[Test]
    public function a_gift_at_the_top_is_taken_out_and_never_counted_again(): void
    {
        $this->app->make(LineSources::class)->register(new FakeSources(static fn (): array => [new GeneratedLine('rule#7', 'test.product', 4, price: 1, label: 'Gift')]));
        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1]]));
        $gift = $this->item($order, 4);

        $refused = $this->refusal(fn () => $this->edit($order, ['qty' => [$gift->id => 2]]));
        $this->assertSame([ItemsEditResult::GENERATED, "qty.{$gift->id}"], [$refused->reason, $refused->result->field]);

        $this->edit($order, ['remove' => [$gift->id]]);
        $this->assertSame(0, $gift->refresh()->qty);
        // A line added is never a rule's gift: the rule's gift for the new line is not taken.
        $this->edit($order, ['add' => [['type' => 'test.product', 'ref' => 3]]]);
        $this->assertSame(0, OrderItem::query()->where('order_id', $order->id)->where('revision', 2)->whereNotNull('source')->count());
    }

    #[Test]
    public function the_units_refunded_stay(): void
    {
        $order = $this->order();
        $itemA = $this->item($order, 1);
        $this->orders()->refund($order, new RefundRequest(0, items: [$itemA->id => 2], source: 'api'));

        // «Item A» × 2, both returned: no fewer than two, and taken out it keeps them.
        $refused = $this->refusal(fn () => $this->edit($order, ['qty' => [$itemA->id => 1]]));
        $this->assertSame([ItemsEditResult::BELOW_REFUNDED, "qty.{$itemA->id}"], [$refused->reason, $refused->result->field]);

        $set = $this->item($order, 2);
        $result = $this->edit($order, ['remove' => [$itemA->id], 'qty' => [$set->id => 2]]);

        $this->assertSame([2, 2], [$itemA->refresh()->qty, $itemA->refunded_qty]);
        $this->assertNotNull($itemA->removed_at);
        $this->assertSame('remove', $result->changes[0]['op']);
        // Shown with the units refunded, as taken out.
        $line = array_values(array_filter(OrderData::of($order->refresh())->items, static fn ($item): bool => $item->id === $itemA->id))[0];
        $this->assertNotNull($line->removedAt);
    }

    #[Test]
    public function a_received_cancelled_or_refunded_order_is_not_edited(): void
    {
        $received = $this->order();
        $this->orders()->mark($received, Milestone::Received, 'api');
        $cancelled = $this->order();
        $this->orders()->cancel($cancelled, 'api', 'Spam');
        $refunded = $this->order();
        $this->orders()->mark($refunded, Milestone::Paid, 'api');
        $this->orders()->refund($refunded, new RefundRequest(24898, source: 'api'));
        $this->assertNotNull($refunded->refresh()->refunded_at);

        foreach ([$received, $cancelled, $refunded] as $order) {
            $itemA = $this->item($order, 1);
            $this->assertSame(ItemsEditResult::STATE, $this->refusal(fn () => $this->edit($order, ['qty' => [$itemA->id => 3]]))->reason);
            $this->assertSame(ItemsEditResult::STATE, $this->edit($order, ['qty' => [$itemA->id => 3]], dryRun: true)->refusal);
        }
    }

    #[Test]
    public function a_paid_order_owes_or_is_owed_the_difference(): void
    {
        $order = $this->order();
        $this->orders()->mark($order, Milestone::Paid, 'panel');
        $itemA = $this->item($order, 1);
        $set = $this->item($order, 2);

        // Up: one more «Item A» — 49.99 less its 2.50 of the discount is to pay.
        $up = $this->edit($order, ['qty' => [$itemA->id => 3]]);

        $order->refresh();
        $this->assertSame([24898, 4749, 4749], [$order->paid_total, $order->balance, $up->balance->due]);
        $this->assertSame('topup', $up->balance->kind());
        $this->assertSame(Balance::stored($order), $order->balance);
        $this->assertNotNull($order->paid_at, 'The milestone stays: it was paid.');
        $this->assertFalse($order->awaiting_payment);
        $this->assertContains(ItemsEditResult::WARN_PAID, $up->warningCodes());

        // Down: the bundle goes — 164.00 is the buyer's to get back.
        $down = $this->edit($order, ['remove' => [$set->id]]);

        $order->refresh();
        $this->assertSame([-11651, 11651], [$order->balance, $down->balance->over]);
        $this->assertSame('over', $down->balance->kind());
        $this->assertSame(Balance::stored($order), $order->balance);
    }

    #[Test]
    public function the_reason_is_kept_when_given(): void
    {
        $order = $this->order();
        $itemA = $this->item($order, 1);
        $notes = fn (): array => StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::ITEMS)->orderBy('id')->pluck('note')->all();

        // Decision 103: no source has to give one — blank is no reason at all.
        foreach (['panel', 'mcp', 'api'] as $number => $source) {
            $this->edit($order, ['qty' => [$itemA->id => 3 + $number], 'reason' => $number === 0 ? '  ' : null, 'source' => $source], true);
            $this->assertSame($number + 1, $this->edit($order, ['qty' => [$itemA->id => 3 + $number], 'reason' => $number === 0 ? '  ' : null, 'source' => $source])->revision);
        }

        $this->edit($order, ['qty' => [$itemA->id => 2], 'reason' => '  The buyer called  ', 'source' => 'panel']);

        $this->assertSame(['revision#1', 'revision#2', 'revision#3', 'revision#4: The buyer called'], $notes());
        $this->assertSame([null, null, null, 'The buyer called'], OrderRevision::query()->where('order_id', $order->id)->orderBy('number')->pluck('reason')->all());
    }

    #[Test]
    public function an_edit_of_nothing_of_everything_or_of_another_order_is_refused(): void
    {
        $order = $this->order();
        $other = $this->order();
        $itemA = $this->item($order, 1);
        $set = $this->item($order, 2);

        $this->assertSame(ItemsEditResult::NOTHING, $this->refusal(fn () => $this->edit($order, []))->reason);
        $this->assertSame(ItemsEditResult::NOTHING, $this->refusal(fn () => $this->edit($order, ['qty' => [$itemA->id => 2]]))->reason);
        $this->assertSame(ItemsEditResult::EMPTY, $this->refusal(fn () => $this->edit($order, ['remove' => [$itemA->id, $set->id]]))->reason);

        $foreign = $this->item($other, 1);
        $refused = $this->refusal(fn () => $this->edit($order, ['remove' => [$foreign->id]]));
        $this->assertSame([ItemsEditResult::FOREIGN, 'remove.0'], [$refused->reason, $refused->result->field]);
    }

    #[Test]
    public function a_line_the_cart_refuses_is_refused_under_add(): void
    {
        $order = $this->order();

        try {
            $this->edit($order, ['add' => [['type' => 'test.product', 'ref' => 999]]]);
            $this->fail('An unknown thing was added.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('add.0.ref', $e->errors());
        }

        $this->assertSame(0, OrderRevision::query()->count());
    }
}
