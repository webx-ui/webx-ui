<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Access\DefaultRoles;
use WebxUi\Auth\Models\Role;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Events\OrderPublicStatusChanged;
use WebxUi\Orders\Events\OrderStatusChanged;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Orders;

/**
 * «Orders» in the panel (spec §11.1–§11.3): the list with its views, search and filters; the card
 * and what it does — each through the service, each with the dry run its dialog shows first.
 */
final class PanelTest extends PanelTestCase
{
    #[Test]
    public function the_list_has_its_views_and_finds_a_buyer_however_they_are_typed(): void
    {
        $new = $this->order(['customer' => new Contact('Anna', 'Petrova', '+800 1234 5678', 'anna@example.test')], [['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 3]]);
        $awaiting = $this->order(['customer' => new Contact('Boris', 'Ivanov', '+800 1234 5679'), 'awaitPayment' => true]);
        $failed = $this->order(['customer' => new Contact('Clara', null, null, 'clara@example.test'), 'awaitPayment' => true]);
        $orders = $this->app->make(Orders::class);
        $orders->paymentFailed($failed, 'gateway', 'declined');
        // A cancelled order still carries the flag (§5.1): it awaits nothing any more.
        $cancelled = $this->order(['customer' => new Contact('Dora', null, '+800 1234 5680'), 'awaitPayment' => true]);
        $orders->cancel($cancelled, 'api');

        $this->signIn(['orders.view']);

        $all = $this->getJson($this->api())->assertOk();
        $this->assertSame(4, $all->json('total'));
        $this->assertSame(['all' => 4, 'new' => 1, 'awaiting' => 2, 'failed' => 1], $all->json('counts'));
        $this->assertSame([$cancelled->id, $failed->id, $awaiting->id, $new->id], array_column($all->json('data'), 'id'));

        $row = self::pick($all->json('data'), 'id', $new->id);
        $this->assertSame(['first' => 'Item A', 'more' => 1], $row['items']);
        $this->assertSame('Anna Petrova', $row['customer']['name']);
        $this->assertSame('New', $row['status']['name']);
        $this->assertSame('Accepted', $row['status']['public']['name']);
        $this->assertSame($this->money(5699), $row['total']);

        $ids = fn (array $query): array => array_column($this->getJson($this->api().'?'.http_build_query($query))->assertOk()->json('data'), 'id');

        $this->assertSame([$new->id], $ids(['view' => 'new']));
        $this->assertSame([$failed->id, $awaiting->id], $ids(['view' => 'awaiting']));
        $this->assertSame([$failed->id], $ids(['view' => 'failed']));
        $this->assertSame([$new->id], $ids(['search' => '8001234 5678']));
        $this->assertSame([$new->id], $ids(['search' => '(800) 1234-5678']));
        $this->assertSame([$new->id], $ids(['search' => 'petrova anna']));
        $this->assertSame([$failed->id], $ids(['search' => 'CLARA@example']));
        $this->assertSame([$awaiting->id], $ids(['search' => (string) $awaiting->number]));
        $this->assertSame([$cancelled->id], $ids(['cancelled' => '1']));
        $this->assertSame([$failed->id, $awaiting->id, $new->id], $ids(['cancelled' => '0']));
        $this->assertSame([$new->id], $ids(['status' => (string) $this->internal('New')->id]));
        $this->assertSame([], $ids(['paid' => '1']));

        $cancelledGroup = PublicStatus::query()->where('code', 'cancelled')->sole();
        $this->assertSame([], $ids(['public' => (string) $cancelledGroup->id]), 'Cancelling marks the order; its status stays where it was.');
    }

    #[Test]
    public function the_card_shows_the_order_and_what_the_reader_may_do_with_it(): void
    {
        $order = $this->order([], [['type' => 'test.product', 'ref' => 1, 'qty' => 2]]);

        $this->signIn(['orders.view']);
        $card = $this->getJson($this->api($order->id))->assertOk()->json('data');

        $this->assertSame($order->number, $card['order']['number']);
        $this->assertSame('Item A', $card['order']['items'][0]['name']);
        $this->assertSame(2, $card['order']['items'][0]['qty']);
        $this->assertSame($this->money(9998), $card['order']['totals']['total']);
        $this->assertSame(['status' => false, 'paid' => false, 'received' => false, 'cancel' => false, 'details' => false, 'refund' => false, 'visitor' => false, 'visitor_search' => false], $card['order']['actions']);
        $this->assertSame('Alex', $card['values']['customer.first_name']);
        $this->assertSame('alex@example.test', $card['values']['customer.email']);
        $this->assertSame(['milestone', 'status'], array_column($card['order']['timeline'], 'kind'));
        $this->assertSame('placed', $card['order']['timeline'][0]['milestone']['key']);
        $this->assertSame(5, count($card['order']['statuses']));

        $this->signIn();
        $actions = $this->getJson($this->api($order->id))->json('data.order.actions');
        $this->assertSame(['status' => true, 'paid' => true, 'received' => true, 'cancel' => true, 'details' => true, 'refund' => true], $actions);

        $this->getJson($this->api(999999))->assertNotFound();
    }

    #[Test]
    public function a_dry_run_of_a_move_says_what_the_buyer_sees_and_writes_nothing(): void
    {
        $order = $this->order();
        $shipped = $this->internal('Handed to delivery');
        PublicStatus::query()->where('code', 'shipped')->sole()->update(['options' => ['notify_channels' => [3]]]);

        $this->signIn();
        $writes = 0;
        DB::listen(static function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete)/i', $query->sql) === 1) {
                $writes++;
            }
        });
        $this->listenFor(OrderStatusChanged::class);
        $this->listenFor(OrderPublicStatusChanged::class);

        $preview = $this->postJson($this->api($order->id).'/status', ['status_id' => $shipped->id, 'dry_run' => true])->assertOk()->json('data');

        $this->assertTrue($preview['moved']);
        $this->assertTrue($preview['public_changed']);
        $this->assertSame('Shipped', $preview['public']);
        $this->assertNull($preview['refusal']);
        $this->assertSame(0, $writes);
        $this->assertSame(0, $this->times(OrderStatusChanged::class));
        $this->assertSame(0, $this->times(OrderPublicStatusChanged::class));

        $inside = $this->postJson($this->api($order->id).'/status', ['status_id' => $this->internal('Awaiting payment')->id, 'dry_run' => true])->json('data');
        $this->assertFalse($inside['public_changed']);

        $card = $this->postJson($this->api($order->id).'/status', ['status_id' => $shipped->id, 'note' => 'Courier at 3'])->assertOk()->json('data.order');
        $this->assertSame('Handed to delivery', $card['status']['name']);
        $this->assertSame('Courier at 3', $card['timeline'][array_key_last($card['timeline'])]['note']);
        $this->assertSame(1, $this->times(OrderPublicStatusChanged::class));
    }

    #[Test]
    public function a_move_the_service_refuses_comes_back_as_the_reason_and_changes_nothing(): void
    {
        $order = $this->order();
        $off = $this->internal('Being packed');
        $off->update(['is_active' => false]);

        $this->signIn();

        $preview = $this->postJson($this->api($order->id).'/status', ['status_id' => $off->id, 'dry_run' => true])->assertOk()->json('data');
        $this->assertSame('inactive', $preview['refusal']);
        $this->assertSame('This status is switched off.', $preview['message']);

        $this->postJson($this->api($order->id).'/status', ['status_id' => $off->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status_id' => 'This status is switched off.']);
        $this->assertSame($this->internal('New')->id, $order->refresh()->status_id);

        $this->postJson($this->api($order->id).'/status', ['status_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('status_id');
    }

    #[Test]
    public function the_selection_moves_with_a_preview_of_how_many_buyers_hear_of_it(): void
    {
        $first = $this->order();
        $second = $this->order();
        $there = $this->order();
        $shipped = $this->internal('Handed to delivery');
        $this->app->make(Orders::class)->moveTo($there, $shipped, 'api');
        PublicStatus::query()->where('code', 'shipped')->sole()->update(['options' => ['notify_channels' => [3, 4]]]);

        $this->signIn();
        $ids = [$first->id, $second->id, $there->id];
        $this->listenFor(OrderStatusChanged::class);

        $preview = $this->postJson($this->api('status'), ['ids' => $ids, 'status_id' => $shipped->id, 'dry_run' => true])->assertOk()->json('data');

        $this->assertSame(['orders' => 3, 'moved' => 2, 'unchanged' => 1, 'public_changed' => 2, 'notifications' => 4, 'milestones' => 0, 'refused' => []], array_intersect_key($preview, array_flip(['orders', 'moved', 'unchanged', 'public_changed', 'notifications', 'milestones', 'refused'])));
        $this->assertSame($this->internal('New')->id, $first->refresh()->status_id);
        $this->assertSame(0, $this->times(OrderStatusChanged::class));

        $done = $this->postJson($this->api('status'), ['ids' => $ids, 'status_id' => $shipped->id])->assertOk()->json('data');
        $this->assertSame(2, $done['moved']);
        $this->assertSame($shipped->id, $first->refresh()->status_id);
        $this->assertSame($shipped->id, $second->refresh()->status_id);
        $this->assertSame(2, $this->times(OrderStatusChanged::class));
    }

    #[Test]
    public function the_selection_is_cancelled_with_a_reason_and_the_paid_among_it_are_counted(): void
    {
        $open = $this->order();
        $paid = $this->order();
        $this->app->make(Orders::class)->mark($paid, Milestone::Paid, 'gateway');

        $this->signIn();

        $this->postJson($this->api('cancel'), ['ids' => [$open->id]])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $preview = $this->postJson($this->api('cancel'), ['ids' => [$open->id, $paid->id], 'reason' => 'Out of stock', 'dry_run' => true])->assertOk()->json('data');
        $this->assertSame([2, 2, 1], [$preview['orders'], $preview['cancelled'], $preview['paid']]);
        $this->assertNull($open->refresh()->cancelled_at);

        $this->postJson($this->api('cancel'), ['ids' => [$open->id, $paid->id], 'reason' => 'Out of stock'])->assertOk();
        $this->assertNotNull($open->refresh()->cancelled_at);
        $this->assertSame('Out of stock', $open->milestones()->where('milestone', 'cancelled')->value('note'));
    }

    #[Test]
    public function a_cancellation_says_how_much_money_is_left_and_wants_a_reason(): void
    {
        $order = $this->order();
        $this->app->make(Orders::class)->mark($order, Milestone::Paid, 'gateway');

        $this->signIn();

        $preview = $this->postJson($this->api($order->id).'/cancel', ['dry_run' => true])->assertOk()->json('data');
        $this->assertTrue($preview['was_paid']);
        $this->assertSame(4999, $preview['money_to_refund']);
        $this->assertSame($this->money(4999), $preview['money_to_refund_text']);
        $this->assertNull($order->refresh()->cancelled_at);

        $this->postJson($this->api($order->id).'/cancel', [])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $card = $this->postJson($this->api($order->id).'/cancel', ['reason' => 'Changed their mind'])->assertOk()->json('data.order');
        $this->assertArrayHasKey('cancelled', $card['milestones']);
        $this->assertFalse($card['actions']['cancel']);
        $this->assertFalse($card['actions']['paid']);
    }

    #[Test]
    public function the_marks_of_payment_and_receipt_are_the_service_s_and_set_once(): void
    {
        $order = $this->order(['awaitPayment' => true]);

        $this->signIn();

        $card = $this->postJson($this->api($order->id).'/milestones', ['milestone' => 'paid', 'reference' => 'TX-1'])->assertOk()->json('data.order');
        $this->assertSame('TX-1', $card['milestones']['paid']['reference']);
        $this->assertFalse($card['awaiting_payment']);
        $this->assertFalse($card['actions']['paid']);

        $this->postJson($this->api($order->id).'/milestones', ['milestone' => 'refunded'])->assertUnprocessable();

        $this->app->make(Orders::class)->cancel($order, 'api');
        $this->postJson($this->api($order->id).'/milestones', ['milestone' => 'received'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['milestone' => 'The order is cancelled: this is not possible any more.']);
    }

    #[Test]
    public function the_contacts_are_saved_under_the_screen_s_names_and_refused_there_too(): void
    {
        $order = $this->order();

        $this->signIn();

        $this->putJson($this->api($order->id).'/details', ['values' => ['customer.phone' => 'call me', 'customer.email' => '']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer.phone']);

        $values = $this->putJson($this->api($order->id).'/details', ['values' => [
            'customer.first_name' => 'Alexa',
            'recipient.first_name' => 'Sam',
            'recipient.phone' => '+800 1234 0000',
            'manager_note' => 'VIP',
        ]])->assertOk()->json('data.values');

        $this->assertSame('Alexa', $values['customer.first_name']);
        $this->assertSame('alex@example.test', $values['customer.email']);
        $this->assertSame('Sam', $values['recipient.first_name']);
        $this->assertSame('VIP', $values['manager_note']);

        // Emptied whole, the recipient is gone: the buyer takes the order.
        $this->putJson($this->api($order->id).'/details', ['values' => ['recipient.first_name' => '', 'recipient.last_name' => '', 'recipient.phone' => '']])->assertOk();
        $this->assertNull($order->refresh()->recipient);
        $this->assertSame('details', $order->history()->reorder('id', 'desc')->value('kind'));
    }

    #[Test]
    public function a_refund_is_typed_in_the_currency_s_units_and_checked_against_what_is_left(): void
    {
        $order = $this->order([], [['type' => 'test.product', 'ref' => 1, 'qty' => 2]]);
        $this->app->make(Orders::class)->mark($order, Milestone::Paid, 'gateway');
        $item = $order->items()->firstOrFail();

        $this->signIn(['orders.view', 'orders.manage']);
        $this->postJson($this->api($order->id).'/refunds', ['amount' => '10'])->assertForbidden();

        $this->signIn(['orders.view', 'orders.refund']);

        $preview = $this->postJson($this->api($order->id).'/refunds', ['amount' => '120.00', 'dry_run' => true])->assertOk()->json('data');
        $this->assertSame('amount', $preview['refusal']);
        $this->assertSame('More than is left to give back: '.$this->money(9998).'.', $preview['message']);

        $this->postJson($this->api($order->id).'/refunds', ['amount' => '120'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount' => 'More than is left to give back: '.$this->money(9998).'.']);

        $this->postJson($this->api($order->id).'/refunds', ['amount' => '0', 'items' => [$item->id => 3]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["items.{$item->id}"]);

        $partial = $this->postJson($this->api($order->id).'/refunds', ['amount' => '49.99', 'items' => [$item->id => 1], 'dry_run' => true])->assertOk()->json('data');
        $this->assertFalse($partial['full']);

        $card = $this->postJson($this->api($order->id).'/refunds', ['amount' => '49.99', 'items' => [$item->id => 1], 'reference' => 'R-1'])->assertOk()->json('data.order');
        $this->assertSame($this->money(4999), $card['totals']['refunded']);
        $this->assertSame(1, $card['items'][0]['refunded_qty']);
        $this->assertSame('refund', $card['timeline'][array_key_last($card['timeline'])]['kind']);

        $full = $this->postJson($this->api($order->id).'/refunds', ['amount' => '49.99', 'dry_run' => true])->json('data');
        $this->assertTrue($full['full']);
        $this->assertSame('refunded', $full['milestone']['key']);
    }

    #[Test]
    public function the_meta_offers_both_sets_of_statuses_the_deleted_ones_marked(): void
    {
        $this->internal('Waiting for a call')->delete();

        $this->signIn(['orders.view']);
        $meta = $this->getJson($this->api('meta'))->assertOk()->json('data');

        $this->assertSame(['accepted', 'in_progress', 'shipped', 'completed', 'cancelled'], array_column($meta['statuses'], 'code'));
        $progress = self::pick($meta['statuses'], 'code', 'in_progress');
        $this->assertTrue(self::pick($progress['statuses'], 'name', 'Waiting for a call')['deleted']);
        $this->assertSame($this->internal('New')->id, $meta['initial_status']);
        $this->assertSame(['delivery' => [], 'payment' => []], $meta['methods']);
        $this->assertFalse($meta['visitors']);
    }

    #[Test]
    public function the_methods_of_the_blocks_filter_the_list(): void
    {
        $courier = $this->order();
        $pickup = $this->order();
        // The blocks are a neighbour's (§6.4); without one registered, the columns are written
        // here directly, as a delivery module would have placed them.
        Order::query()->whereKey($courier->id)->update(['delivery' => json_encode(['method' => 'courier'])]);
        Order::query()->whereKey($pickup->id)->update(['delivery' => json_encode(['method' => 'pickup'])]);

        $this->signIn(['orders.view']);

        $this->assertSame(['courier', 'pickup'], $this->getJson($this->api('meta'))->json('data.methods.delivery'));
        $this->assertSame([$pickup->id], array_column($this->getJson($this->api().'?delivery=pickup')->json('data'), 'id'));
        $this->assertSame('pickup', self::pick($this->getJson($this->api())->json('data'), 'id', $pickup->id)['delivery']);
    }

    #[Test]
    public function the_order_manager_is_a_ready_made_role_of_its_own(): void
    {
        $definitions = $this->app->make(DefaultRoles::class)->definitions();

        $this->assertSame(['orders.view', 'orders.manage', 'users.view'], $definitions['orders']);

        $this->app->make(DefaultRoles::class)->seed();

        // Without the visitors installed there is no `users.view` to give.
        $this->assertSame(['orders.view', 'orders.manage'], Role::query()->where('slug', 'orders')->sole()->permissions);
        $this->assertNotContains('orders.refund', Role::query()->where('slug', 'orders')->sole()->permissions);
    }
}
