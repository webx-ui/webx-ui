<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderRefundReversed;
use WebxUi\Orders\Events\OrderStatusChanged;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Orders;
use WebxUi\Orders\RefundRequest;

/**
 * The orders for an agent (spec §12): every tool a thin wrapper of `Orders` and `OrderStatuses`,
 * so that its dry run is the panel dialog's answer word for word, its refusal the panel's words,
 * and a dry run changes nothing.
 */
final class McpTest extends McpTestCase
{
    /** The tools of §12 but `orders_link_visitor`, which comes with the visitors, and of §32. */
    private const array TOOLS = [
        'orders_list', 'orders_get', 'orders_history', 'orders_summary', 'orders_set_status', 'orders_mark',
        'orders_cancel', 'orders_refund', 'orders_refund_reverse', 'orders_update', 'orders_create', 'orders_note',
        'orders_statuses_list', 'orders_statuses_save', 'orders_statuses_delete', 'orders_statuses_restore', 'orders_statuses_reorder',
        'orders_public_statuses_save', 'orders_public_statuses_delete', 'orders_public_statuses_restore', 'orders_public_statuses_reorder',
        'orders_settings_get', 'orders_settings_set',
        // The checkout's (§32), while it is on.
        'orders_checkout_steps', 'orders_checkout_preview', 'orders_checkout_funnel', 'orders_checkout_drafts_list',
        'orders_checkout_settings_get', 'orders_checkout_settings_set',
    ];

    #[Test]
    public function the_command_lists_every_tool_of_the_orders(): void
    {
        $this->assertSame(0, Artisan::call('webx:mcp-tools', ['--module' => 'orders']));
        $output = Artisan::output();

        foreach (self::TOOLS as $tool) {
            $this->assertMatchesRegularExpression('/^\| '.$tool.' +\| orders /m', $output, "{$tool} is listed");
        }

        $this->assertStringContainsString('Tools ', $output);
        $this->assertSame(count(self::TOOLS), preg_match_all('/^\| orders_[a-z_]+ +\| orders /m', $output));
    }

    #[Test]
    public function one_move_answers_what_the_panel_s_dialog_shows(): void
    {
        $order = $this->order();
        $shipped = $this->internal('Handed to delivery');
        $this->signIn();

        $panel = $this->postJson($this->api($order->id).'/status', ['status_id' => $shipped->id, 'dry_run' => true])->assertOk()->json('data');
        $agent = $this->ask('orders_set_status', ['order' => $order->number, 'status' => $shipped->id, 'dry_run' => true]);

        foreach ($panel as $key => $value) {
            $this->assertSame($value, $agent[$key], "orders_set_status says {$key} as the panel does");
        }

        $this->assertTrue($agent['public_changed']);
        $this->assertSame($order->status_id, $order->refresh()->status_id);

        // By its name, and for real: the same move.
        $moved = $this->ask('orders_set_status', ['order' => $order->id, 'status' => 'handed to delivery', 'note' => 'Courier took it']);
        $this->assertTrue($moved['moved']);
        $this->assertFalse($moved['dry_run']);
        $this->assertSame($shipped->id, $order->refresh()->status_id);
    }

    #[Test]
    public function a_batch_on_a_dry_run_changes_nothing_and_counts_as_the_selection_does(): void
    {
        $orders = [$this->order(), $this->order(), $this->order()];
        $packed = $this->internal('Being packed');
        $this->app->make(Orders::class)->moveTo($orders[2], $packed, 'api');
        $this->signIn();
        $this->listenFor(OrderStatusChanged::class);

        $panel = $this->postJson($this->api('status'), ['ids' => array_map(static fn (Order $one): int => $one->id, $orders), 'status_id' => $packed->id, 'dry_run' => true])->assertOk()->json('data');
        $numbers = array_map(static fn (Order $one): string => (string) $one->number, $orders);
        $agent = $this->writesNothing(fn (): array => $this->ask('orders_set_status', ['numbers' => $numbers, 'status' => $packed->id, 'dry_run' => true]));

        $this->assertSame($panel, $agent);
        $this->assertSame(['orders' => 3, 'moved' => 2, 'unchanged' => 1], array_intersect_key($agent, ['orders' => 0, 'moved' => 0, 'unchanged' => 0]));
        $this->assertSame(0, $this->times(OrderStatusChanged::class));

        $done = $this->ask('orders_set_status', ['numbers' => $numbers, 'status' => $packed->id]);
        $this->assertSame(2, $done['moved']);
        $this->assertSame(2, $this->times(OrderStatusChanged::class));
    }

    #[Test]
    public function a_status_that_cancels_wants_the_reason_from_the_agent_even_on_a_dry_run(): void
    {
        $order = $this->order();
        $cancelled = $this->internal('Cancelled');

        $this->agent('orders_set_status', ['order' => $order->number, 'status' => $cancelled->id, 'dry_run' => true])->assertHasErrors(['note']);
        $this->agent('orders_set_status', ['numbers' => [$order->number], 'status' => $cancelled->id])->assertHasErrors(['note']);
        $this->assertNull($order->refresh()->cancelled_at);

        $this->ask('orders_set_status', ['order' => $order->number, 'status' => $cancelled->id, 'note' => 'The buyer called it off']);
        $this->assertNotNull($order->refresh()->cancelled_at);

        // A deleted status refuses in the panel's words, with the order it is about.
        $deleted = $this->internal('Waiting for a call');
        $deleted->delete();
        $this->agent('orders_set_status', ['order' => $order->number, 'status' => $deleted->id])
            ->assertHasErrors(["Order {$order->number}: This status is deleted."]);
    }

    #[Test]
    public function a_mark_on_a_dry_run_names_its_event_and_a_repeat_is_silence(): void
    {
        $order = $this->order();
        $this->listenFor(OrderPaid::class);

        $preview = $this->writesNothing(fn (): array => $this->ask('orders_mark', ['order' => $order->number, 'milestone' => 'paid', 'dry_run' => true]));
        $this->assertSame('marked', $preview['outcome']);
        $this->assertSame([OrderPaid::class], $preview['events']);
        $this->assertContains('Closure', $preview['listeners']);
        $this->assertSame(0, $this->times(OrderPaid::class));

        $this->assertSame('marked', $this->ask('orders_mark', ['order' => $order->number, 'milestone' => 'paid', 'reference' => 'BANK-7'])['outcome']);
        $this->assertSame('already_marked', $this->ask('orders_mark', ['order' => $order->number, 'milestone' => 'paid'])['outcome']);
        $this->assertSame(1, $this->times(OrderPaid::class));
        $this->assertSame('BANK-7', $order->refresh()->milestones()->where('milestone', 'paid')->value('reference'));

        $this->agent('orders_mark', ['order' => $order->number, 'milestone' => 'cancelled'])->assertHasErrors(['paid or received']);
    }

    #[Test]
    public function a_cancellation_and_a_refund_answer_what_the_dialogs_do(): void
    {
        $order = $this->order();
        $this->app->make(Orders::class)->mark($order, Milestone::Paid, 'api');
        $this->signIn();
        $this->listenFor(OrderCancelled::class);

        $panel = $this->postJson($this->api($order->id).'/cancel', ['reason' => 'Out of stock', 'dry_run' => true])->assertOk()->json('data');
        $agent = $this->writesNothing(fn (): array => $this->ask('orders_cancel', ['order' => $order->number, 'reason' => 'Out of stock', 'dry_run' => true]));
        $this->assertSame($panel['was_paid'], $agent['was_paid']);
        $this->assertSame($panel['money_to_refund'], $agent['money_to_refund']);
        $this->assertSame($panel['money_to_refund_text'], $agent['money_to_refund_formatted']);
        $this->assertSame($panel['status'], $agent['status']);
        $this->assertSame('Cancelled', $agent['status']['name']);
        $this->agent('orders_cancel', ['order' => $order->number, 'reason' => '  '])->assertHasErrors(['reason']);

        $this->ask('orders_cancel', ['order' => $order->number, 'reason' => 'Out of stock']);
        $this->assertSame(1, $this->times(OrderCancelled::class));

        $item = $order->items()->firstOrFail();
        $too = $this->writesNothing(fn (): array => $this->ask('orders_refund', ['order' => $order->number, 'amount' => 6000, 'dry_run' => true]));
        $this->assertSame('amount', $too['refusal']);
        $this->assertSame($this->postJson($this->api($order->id).'/refunds', ['amount' => '60', 'dry_run' => true])->json('data.message'), $too['message']);
        $this->agent('orders_refund', ['order' => $order->number, 'amount' => 6000])->assertHasErrors(["Order {$order->number}: More than is left to give back: ".$this->money(4999)]);

        $full = $this->ask('orders_refund', ['order' => $order->number, 'amount' => 4999, 'items' => [(string) $item->id => 1], 'reference' => 'TR-1']);
        $this->assertTrue($full['full']);
        $this->assertSame(4999, $full['money_left']);
        $this->assertNotNull($order->refresh()->refunded_at);
    }

    #[Test]
    public function a_refund_reversed_answers_what_the_dialog_does(): void
    {
        $order = $this->order();
        $orders = $this->app->make(Orders::class);
        $orders->mark($order, Milestone::Paid, 'api');
        $refund = $orders->refund($order, new RefundRequest(4999, source: 'api'));
        $other = $this->order();
        $orders->mark($other, Milestone::Paid, 'api');
        $theirs = $orders->refund($other, new RefundRequest(1000, source: 'api'));
        $this->signIn();
        $this->listenFor(OrderRefundReversed::class);
        $reverse = fn (int $id): string => $this->api($order->id)."/refunds/{$id}/reverse";

        $panel = $this->postJson($reverse((int) $refund->refundId), ['dry_run' => true])->assertOk()->json('data');
        $agent = $this->writesNothing(fn (): array => $this->ask('orders_refund_reverse', ['order' => $order->number, 'refund' => $refund->refundId, 'reason' => 'Wrong order', 'dry_run' => true]));
        unset($agent['number']);
        $this->assertSame($panel, $agent);
        $this->assertSame(['refunded', 4999, 0], [$agent['milestone_removed']['key'], $agent['money_left'], $agent['refunded_total']]);

        // Another order's record: the same refusal in the same words.
        $foreign = $this->postJson($reverse((int) $theirs->refundId), ['dry_run' => true])->assertOk()->json('data');
        $this->assertSame('foreign', $foreign['refusal']);
        $this->assertSame($foreign['message'], $this->ask('orders_refund_reverse', ['order' => $order->number, 'refund' => $theirs->refundId, 'reason' => 'x', 'dry_run' => true])['message']);
        $this->postJson($reverse((int) $theirs->refundId), ['reason' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['refund' => $foreign['message']]);
        $this->agent('orders_refund_reverse', ['order' => $order->number, 'refund' => $theirs->refundId, 'reason' => 'x'])->assertHasErrors(["Order {$order->number}: {$foreign['message']}"]);
        $this->agent('orders_refund_reverse', ['order' => $order->number, 'refund' => $refund->refundId, 'reason' => ' '])->assertHasErrors(['reason']);
        $this->postJson($reverse((int) $refund->refundId), ['reason' => ''])->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $done = $this->ask('orders_refund_reverse', ['order' => $order->number, 'refund' => $refund->refundId, 'reason' => 'Wrong order']);
        $this->assertFalse($done['dry_run']);
        $this->assertSame(1, $this->times(OrderRefundReversed::class));

        // Reversed already: the panel refuses as the agent does.
        $this->postJson($reverse((int) $refund->refundId), ['reason' => 'Again'])->assertUnprocessable()->assertJsonValidationErrors(['refund']);
        $this->agent('orders_refund_reverse', ['order' => $order->number, 'refund' => $refund->refundId, 'reason' => 'Again'])->assertHasErrors(['reversed already']);

        // orders_get and orders_history show the record struck through, and why.
        $got = $this->ask('orders_get', ['order' => $order->number]);
        $this->assertSame(['mcp', 'Wrong order'], [$got['refunds'][0]['reversed']['source'], $got['refunds'][0]['reversed']['reason']]);
        $this->assertSame(0, $got['totals']['refunded_total']);
        $timeline = $this->ask('orders_history', ['order' => $order->number])['timeline'];
        $kinds = array_column($timeline, 'kind');
        $this->assertContains('refund_reversed', $kinds);
        $this->assertContains('milestone_removed', $kinds);
        $line = $timeline[array_search('refund_reversed', $kinds, true)];
        $this->assertSame([4999, $this->money(4999), 'Wrong order', $refund->refundId], [$line['amount'], $line['amount_formatted'], $line['note'], $line['refund_id']]);
        $this->assertTrue($timeline[array_search('refund', $kinds, true)]['reversed']);
    }

    #[Test]
    public function the_contacts_change_and_a_dry_run_only_says_which(): void
    {
        $order = $this->order();

        // Rehearsed and rolled back: the history and the journal it would write are not kept.
        $preview = $this->ask('orders_update', ['order' => $order->number, 'customer' => ['phone' => '+1 555 010-9999'], 'manager_note' => 'VIP', 'dry_run' => true]);
        $this->assertSame(['customer.phone', 'manager_note'], $preview['changed']);
        $this->assertSame('+1 555 010-0000', $order->refresh()->customer_phone);
        $this->assertSame(1, $order->history()->count());

        $this->ask('orders_update', ['order' => $order->number, 'customer' => ['phone' => '+1 555 010-9999'], 'recipient' => ['first_name' => 'Sam']]);
        $order->refresh();
        $this->assertSame('+1 555 010-9999', $order->customer_phone);
        $this->assertSame('Alex', $order->customer_first_name);
        $this->assertSame('Sam', $order->recipient['first_name'] ?? null);

        // The manager note as before; the panel pins it over the feed of the card (decision 91).
        $this->ask('orders_update', ['order' => $order->number, 'manager_note' => 'VIP']);
        $this->assertSame('VIP', $order->refresh()->manager_note);
        $this->actingAs($this->caller(), 'cms');
        $this->assertSame('VIP', $this->getJson($this->api($order->id))->assertOk()->json('data.order.manager_note'));

        $this->agent('orders_update', ['order' => $order->number, 'customer' => ['email' => 'not an address']])->assertHasErrors(['customer.email']);
        $this->agent('orders_update', ['order' => $order->number])->assertHasErrors(['Nothing to change']);
        $this->agent('orders_update', ['order' => $order->number, 'customer' => ['phone2' => '1']])->assertHasErrors(['phone2']);

        $this->ask('orders_note', ['order' => $order->number, 'text' => 'Called, will come on Friday']);
        $timeline = $this->ask('orders_history', ['order' => $order->number])['timeline'];
        $this->assertSame('Called, will come on Friday', self::pick($timeline, 'kind', 'note')['note']);
    }

    #[Test]
    public function the_lists_read_with_money_in_minor_units_and_the_buyers_contacts(): void
    {
        $first = $this->order();
        $this->order([], [['type' => 'test.product', 'ref' => 2]]);

        $list = $this->ask('orders_list', ['search' => '555 010', 'sort' => 'total']);
        $this->assertSame(2, $list['total']);
        $this->assertSame([4999, 15000], array_column($list['orders'], 'total'));
        $this->assertSame($this->money(4999), $list['orders'][0]['total_formatted']);
        $this->assertSame('alex@example.test', $list['orders'][0]['customer']['email']);
        $this->assertSame(['all' => 2, 'new' => 2, 'awaiting' => 0, 'failed' => 0], $list['counts']);
        $this->assertSame(0, $this->ask('orders_list', ['public' => ['shipped']])['total']);
        $this->agent('orders_list', ['public' => ['nowhere']])->assertHasErrors(['nowhere']);
        $this->agent('orders_list', ['from' => 'yesterday'])->assertHasErrors(['from']);

        $order = $this->ask('orders_get', ['order' => $first->number]);
        $this->assertSame('Item A', $order['items'][0]['name']);
        $this->assertSame(4999, $order['totals']['payable']);
        $this->assertSame('accepted', $order['status']['public']['code']);
        $this->assertArrayHasKey('placed', $order['milestones']);
        $this->agent('orders_get', ['order' => 'NOPE-1'])->assertHasErrors(['There is no order NOPE-1']);

        $summary = $this->ask('orders_summary');
        $this->assertSame(2, self::pick($summary['public'], 'code', 'accepted')['orders']);
        $this->assertSame(2, $summary['periods'][0]['by_currency'][0]['placed']);
        $this->assertSame(0, $summary['periods'][2]['by_currency'][0]['revenue']);
    }

    #[Test]
    public function the_statuses_answer_as_the_screen_does_and_refuse_in_its_words(): void
    {
        $order = $this->order();
        $packed = $this->internal('Being packed');
        $this->app->make(Orders::class)->moveTo($order, $packed, 'api');
        $this->signIn(['orders.statuses']);
        $statuses = $this->api('statuses');

        $this->assertSame($this->getJson($statuses)->json('data'), $this->ask('orders_statuses_list'));

        // Delete: the consequences of the dialog, and nothing deleted.
        $panel = $this->deleteJson($statuses.'/internal/'.$packed->id, ['dry_run' => true])->json('data');
        $agent = $this->writesNothing(fn (): array => $this->ask('orders_statuses_delete', ['id' => $packed->id, 'dry_run' => true]));
        $this->assertSame([...$panel, 'dry_run' => true], $agent);

        // Moved to another public group: whose buyers see it, counted the same.
        $shipped = PublicStatus::query()->where('code', 'shipped')->firstOrFail();
        $panel = $this->putJson($statuses.'/internal/'.$packed->id, [
            'public_status_id' => $shipped->id, 'name' => $packed->getTranslations('name'), 'color' => $packed->color->value, 'is_active' => true, 'dry_run' => true,
        ])->json('data');
        $agent = $this->ask('orders_statuses_save', ['id' => $packed->id, 'public_code' => 'shipped', 'dry_run' => true]);
        $this->assertSame($panel['lines'], $agent['lines']);
        $this->assertSame($panel['rebound'], $agent['rebound']);
        $this->assertSame($packed->public_status_id, $packed->refresh()->public_status_id);

        // The status new orders come into: the panel's refusal word for word.
        $new = $this->internal('New');
        $said = $this->deleteJson($statuses.'/internal/'.$new->id)->assertUnprocessable()->json('errors.status.0');
        $this->agent('orders_statuses_delete', ['id' => $new->id])->assertHasErrors([$said]);

        // A public group with live stages names them.
        $this->agent('orders_public_statuses_delete', ['code' => 'in_progress'])->assertHasErrors(['Its statuses: Taken into work, Waiting for a call, Being packed.']);

        // A new one, then reordered to the front of its group.
        $created = $this->ask('orders_statuses_save', ['public_code' => 'in_progress', 'name' => ['en' => 'On hold'], 'color' => 'warning']);
        $id = $created['status']['id'];
        $this->ask('orders_statuses_reorder', ['public_code' => 'in_progress', 'ids' => [$id]]);
        $group = self::pick($this->ask('orders_statuses_list')['groups'], 'code', 'in_progress');
        $this->assertSame(['On hold', 'Taken into work', 'Waiting for a call', 'Being packed'], array_column($group['statuses'], 'label'));

        // Public: written on a dry run, nothing kept; reordered.
        $this->assertTrue($this->ask('orders_public_statuses_save', ['code' => 'on_the_way', 'name' => ['en' => 'On the way'], 'dry_run' => true])['created']);
        $this->assertFalse(PublicStatus::withTrashed()->where('code', 'on_the_way')->exists());
        $this->ask('orders_public_statuses_reorder', ['codes' => ['cancelled']]);
        $this->assertSame('cancelled', PublicStatus::query()->orderBy('position')->value('code'));

        $this->agent('orders_statuses_save', ['name' => ['en' => 'Lost']])->assertHasErrors(['public_code']);
        $this->assertSame(1, OrderStatus::query()->where('is_active', true)->whereKey($id)->count());
    }

    #[Test]
    public function the_settings_without_the_settings_module_are_the_config_s(): void
    {
        $settings = $this->ask('orders_settings_get');
        $this->assertFalse($settings['stored']);
        $this->assertSame(1000, $settings['values']['orders.number.start']);

        $this->agent('orders_settings_set', ['values' => ['orders.number.prefix' => 'A-'], 'dry_run' => true])->assertHasErrors(['module-settings']);
        $this->agent('orders_settings_set', ['values' => ['orders.prefix' => 'A-']])->assertHasErrors(['orders.prefix']);
    }

    #[Test]
    public function the_catalog_is_read_off_the_enums_and_the_tables(): void
    {
        $catalog = ($this->resource('orders://catalog')->handler)();

        $this->assertSame(array_map(static fn (Milestone $one): string => $one->value, Milestone::cases()), array_column($catalog['milestones'], 'key'));
        $this->assertSame(OrderPaid::class, self::pick($catalog['milestones'], 'key', 'paid')['event']);
        $this->assertSame(['accepted', 'in_progress', 'shipped', 'completed', 'cancelled'], array_column($catalog['statuses'], 'code'));
        $this->assertSame(['delivery', 'payment'], array_column($catalog['blocks'], 'key'));
        $this->assertCount(6, $catalog['colors']);
    }

    #[Test]
    public function reading_does_not_open_the_writes_and_money_is_apart(): void
    {
        $order = $this->order();
        $reader = $this->manager(['orders.view']);

        $this->agent('orders_get', ['order' => $order->number], $reader)->assertOk();
        $this->agent('orders_set_status', ['order' => $order->number, 'status' => 1, 'dry_run' => true], $reader)->assertHasErrors();
        $this->agent('orders_statuses_delete', ['id' => 1, 'dry_run' => true], $reader)->assertHasErrors();
        $this->agent('orders_refund', ['order' => $order->number, 'amount' => 1, 'dry_run' => true], $this->manager(['orders.view', 'orders.manage']))->assertHasErrors();
        $this->agent('orders_refund', ['order' => $order->number, 'amount' => 1, 'dry_run' => true], $this->manager(['orders.refund']))->assertOk();
    }

    /**
     * Run the call and fail when it wrote to any table but the journal of agent calls.
     *
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    private function writesNothing(Closure $call): mixed
    {
        $this->caller();
        $writes = [];
        DB::listen(static function ($query) use (&$writes): void {
            $sql = strtolower(ltrim($query->sql));

            if (preg_match('/^(insert|update|delete|replace)\b/', $sql) === 1 && ! str_contains($sql, 'mcp_calls')) {
                $writes[] = $query->sql;
            }
        });

        $result = $call();
        $this->assertSame([], $writes, 'A dry run wrote.');

        return $result;
    }
}
