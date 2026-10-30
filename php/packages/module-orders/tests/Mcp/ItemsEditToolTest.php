<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Orders;
use WebxUi\Orders\RefundRequest;

/**
 * «Change the lines» of the card and `orders_items_edit` (spec §42, §43): both wrap
 * `Orders::editItems()`, so the editor's live totals and the agent's dry run are one answer;
 * the save answers the card with the edit beside it, the timeline and the list show the edit, and
 * an edit made meanwhile is refused under `revision` in both.
 */
final class ItemsEditToolTest extends McpTestCase
{
    /** «Item A» × 2 and «Part C» × 1: 2 × 49.99 + 7.00 = 106.98. */
    private function twoLines(): Order
    {
        return $this->order(items: [
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2],
            ['type' => 'test.product', 'ref' => 3],
        ]);
    }

    private function line(Order $order, int $ref): OrderItem
    {
        return OrderItem::query()->where('order_id', $order->id)->where('ref_id', $ref)->orderBy('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function preview(Order $order, array $body): array
    {
        return $this->postJson($this->api($order->id).'/items/preview', ['revision' => 0, ...$body])->assertOk()->json('data');
    }

    #[Test]
    public function the_editor_and_an_agent_say_the_same_of_an_edit(): void
    {
        $order = $this->twoLines();
        $this->app->make(Orders::class)->mark($order, Milestone::Paid, 'api');
        $item = $this->line($order, 1);
        $this->signIn();

        // Fewer units of a paid order: the money to give back, and the warning that it was paid.
        $panel = $this->preview($order, ['qty' => [$item->id => 1]]);
        $agent = $this->ask('orders_items_edit', ['order' => $order->number, 'revision' => 0, 'qty' => [(string) $item->id => 1], 'reason' => 'The buyer called', 'dry_run' => true]);
        unset($agent['number']);
        $this->assertSame($panel, $agent);
        $this->assertSame([['qty', 2, 1]], array_map(static fn (array $change): array => [$change['op'], $change['qty_from'], $change['qty_to']], $panel['changes']));
        $this->assertSame([10698, 5699, 'over', 4999], [$panel['before']['total'], $panel['after']['total'], $panel['balance']['kind'], $panel['balance']['over']]);
        $this->assertSame(['paid'], array_column($panel['warnings'], 'code'));
        $this->assertSame(
            __('webx-orders::panel.items-warnings.paid-over', ['paid' => $this->money(10698), 'payable' => $this->money(5699), 'over' => $this->money(4999)]),
            $panel['warnings'][0]['message'],
        );
        $this->assertTrue($panel['dry_run']);

        // A line added comes through the cart of the edit, which goes with the dry run.
        $carts = DB::table('carts')->count();
        $panel = $this->preview($order, ['add' => [['type' => 'test.product', 'ref' => 4]]]);
        $agent = $this->ask('orders_items_edit', ['order' => $order->number, 'revision' => 0, 'add' => [['type' => 'test.product', 'ref' => 4]], 'reason' => 'x', 'dry_run' => true]);
        unset($agent['number']);
        $this->assertSame($panel, $agent);
        $this->assertSame(['add', 'Gift D', 2500, 'topup'], [$panel['changes'][0]['op'], $panel['changes'][0]['name'], $panel['changes'][0]['total_to'], $panel['balance']['kind']]);
        $this->assertSame([$carts, 0, 0], [DB::table('carts')->count(), DB::table('order_revisions')->count(), $order->refresh()->items_revision]);

        // A refusal is an answer of the dry run, in the same words.
        $panel = $this->preview($order, ['qty' => [$item->id => 2]]);
        $this->assertSame(['nothing', __('webx-orders::panel.items-refusals.nothing')], [$panel['refusal'], $panel['message']]);
        $this->assertSame($panel['message'], $this->ask('orders_items_edit', ['order' => $order->number, 'revision' => 0, 'qty' => [(string) $item->id => 2], 'reason' => 'x', 'dry_run' => true])['message']);
    }

    #[Test]
    public function the_save_answers_the_card_and_shows_the_edit_everywhere(): void
    {
        $order = $this->twoLines();
        $part = $this->line($order, 3);
        $this->signIn();

        $data = $this->putJson($this->api($order->id).'/items', [
            'revision' => 0,
            'remove' => [$part->id],
            'add' => [['type' => 'test.product', 'ref' => 4, 'qty' => 2]],
            'reason' => 'Part C is out of stock',
            'message' => 'We swapped Part C for Gift D.',
            'notify' => false,
        ])->assertOk()->json('data');

        $this->assertSame([1, null], [$data['edit']['revision'], $data['edit']['refusal']]);
        $this->assertSame(1, $data['order']['items_revision']);
        // The line taken out whole is gone from «Items»; its edit is in the history.
        $this->assertSame(['Item A', 'Gift D'], array_column($data['order']['items'], 'name'));
        $this->assertSame(['lines' => 2, 'units' => 4, 'refunded_units' => 0], $data['order']['count']);

        $line = self::pick($data['order']['timeline'], 'kind', 'items');
        $this->assertSame('Part C is out of stock', $line['note']);
        $this->assertSame([1, $this->money(10698), $this->money(14998)], [$line['revision']['number'], $line['revision']['total_from'], $line['revision']['total_to']]);
        $this->assertSame(['remove', 'add'], array_column($line['revision']['changes'], 'op'));
        $this->assertSame('We swapped Part C for Gift D.', $line['revision']['message']);

        $row = self::pick($this->getJson($this->api())->assertOk()->json('data'), 'id', $order->id);
        $this->assertTrue($row['edited']);
        $this->assertSame(['first' => 'Item A', 'more' => 1], $row['items']);

        $glance = $this->getJson($this->api($order->id).'/glance')->assertOk()->json('data');
        $this->assertSame([1, ['Item A', 'Gift D']], [$glance['items_revision'], array_column($glance['items'], 'name')]);

        // The agent reads the same: the revision to send back, the lines left, the edit in full.
        $got = $this->ask('orders_get', ['order' => $order->number]);
        $this->assertSame([1, ['Item A', 'Gift D']], [$got['items_revision'], array_column($got['items'], 'name')]);
        $history = $this->ask('orders_history', ['order' => $order->number]);
        $this->assertSame(['Part C is out of stock', 'We swapped Part C for Gift D.', 'panel'], [$history['revisions'][0]['reason'], $history['revisions'][0]['message'], $history['revisions'][0]['source']]);
        $this->assertSame(1, self::pick($history['timeline'], 'kind', 'items')['revision']['number']);

        // The same revision a second time — a tab opened before the save: refused, nothing written.
        $stale = __('webx-orders::panel.items-refusals.stale');
        $this->putJson($this->api($order->id).'/items', ['revision' => 0, 'qty' => [$this->line($order, 1)->id => 3], 'reason' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['revision' => $stale]);
        $this->agent('orders_items_edit', ['order' => $order->number, 'revision' => 0, 'qty' => [(string) $this->line($order, 1)->id => 3], 'reason' => 'x'])
            ->assertHasErrors(["Order {$order->number}: {$stale} (revision)"]);
        $this->assertSame(1, $order->refresh()->items_revision);

        // The agent's edit is a second one; the reason may be left out (decision 103).
        $done = $this->ask('orders_items_edit', ['order' => $order->number, 'revision' => 1, 'qty' => [(string) $this->line($order, 1)->id => 3]]);
        $this->assertSame([2, false], [$done['revision'], $done['dry_run']]);
        $this->assertNull($order->revisions()->where('number', 2)->value('reason'));

        // A panel edit without one is saved too, and its line in the history carries no reason.
        $timeline = $this->putJson($this->api($order->id).'/items', ['revision' => 2, 'qty' => [$this->line($order, 1)->id => 2]])->assertOk()->json('data.order.timeline');
        $lines = array_values(array_filter($timeline, static fn (array $line): bool => ($line['revision']['number'] ?? null) === 3));
        $this->assertCount(1, $lines);
        $this->assertNull($lines[0]['note']);
    }

    #[Test]
    public function the_action_is_the_manager_s_and_greyed_past_editing(): void
    {
        $order = $this->twoLines();

        $this->signIn(['orders.view']);
        $this->assertFalse($this->getJson($this->api($order->id))->assertOk()->json('data.order.actions.edit_items'));
        $this->postJson($this->api($order->id).'/items/preview', ['revision' => 0, 'remove' => [1]])->assertForbidden();
        $this->putJson($this->api($order->id).'/items', ['revision' => 0, 'remove' => [1], 'reason' => 'x'])->assertForbidden();
        $this->agent('orders_items_edit', ['order' => $order->number, 'revision' => 0, 'remove' => [1], 'reason' => 'x'], $this->manager(['orders.view']))->assertHasErrors();

        $this->signIn(['orders.view', 'orders.manage']);
        $actions = $this->getJson($this->api($order->id))->json('data.order.actions');
        $this->assertSame([true, null], [$actions['edit_items'], $actions['edit_items_reason']]);

        $this->app->make(Orders::class)->mark($order, Milestone::Received, 'api');
        $actions = $this->getJson($this->api($order->id))->json('data.order.actions');
        $this->assertSame([true, __('webx-orders::panel.items-refusals.state')], [$actions['edit_items'], $actions['edit_items_reason']]);
        $this->assertSame('state', $this->preview($order, ['remove' => [$this->line($order, 3)->id]])['refusal']);
    }

    #[Test]
    public function a_line_with_units_returned_keeps_them_when_taken_out(): void
    {
        $order = $this->twoLines();
        $orders = $this->app->make(Orders::class);
        $orders->mark($order, Milestone::Paid, 'api');
        $item = $this->line($order, 1);
        $orders->refund($order, new RefundRequest(4999, items: [$item->id => 1], source: 'api'));
        $this->signIn();

        // «0» is «take it out»: the unit returned stays, so the line comes to 1, not 0.
        $out = $this->preview($order, ['qty' => [$item->id => 0]]);
        $this->assertSame([null, 'remove', 2, 1], [$out['refusal'], $out['changes'][0]['op'], $out['changes'][0]['qty_from'], $out['changes'][0]['qty_to']]);

        $data = $this->putJson($this->api($order->id).'/items', ['revision' => 0, 'remove' => [$item->id], 'reason' => 'Gone'])->assertOk()->json('data');
        // Taken out, the line keeps the unit returned and still shows, with what was returned of it.
        $shown = self::pick($data['order']['items'], 'id', $item->id);
        $this->assertSame([1, 1, true], [$shown['qty'], $shown['refunded_qty'], $shown['removed']]);
    }
}
