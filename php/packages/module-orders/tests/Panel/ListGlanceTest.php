<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderDetails;
use WebxUi\Orders\Orders;

/**
 * «Orders» after the review of 30.10.2026 (webx-ui#92): the tab a list opened without one lands
 * on (decision 92), the opened row (decision 94) and the pinned note of the card (decision 91).
 */
final class ListGlanceTest extends PanelTestCase
{
    use CountsQueries;

    #[Test]
    public function without_a_tab_the_list_is_on_new_while_it_holds_any_order(): void
    {
        $this->signIn(['orders.view']);
        $fresh = $this->order();
        $moved = $this->order();
        $this->app->make(Orders::class)->moveTo($moved, $this->internal('Being packed'), 'api');

        $auto = $this->getJson($this->api().'?view=auto')->assertOk();
        $this->assertSame('new', $auto->json('view'));
        $this->assertSame([$fresh->id], array_column($auto->json('data'), 'id'));
        $this->assertSame(['all' => 2, 'new' => 1, 'awaiting' => 0, 'failed' => 0], $auto->json('counts'));

        // A tab asked for is the tab, whatever «New» holds.
        $this->assertSame('all', $this->getJson($this->api().'?view=all')->json('view'));
        $this->assertCount(2, $this->getJson($this->api().'?view=all')->json('data'));

        // Nothing new: «All».
        $this->app->make(Orders::class)->moveTo($fresh, $this->internal('Being packed'), 'api');
        $none = $this->getJson($this->api().'?view=auto')->assertOk();
        $this->assertSame('all', $none->json('view'));
        $this->assertCount(2, $none->json('data'));
    }

    #[Test]
    public function an_opened_row_shows_what_the_order_holds_and_who_it_is_for(): void
    {
        $orders = $this->app->make(Orders::class);
        $order = $this->order([
            'customer' => new Contact('Alex', 'Doe', '+800 1234 5678', 'alex@example.com'),
            'recipient' => new Contact('Sam', 'Roe', '+800 1234 5679'),
            'comment' => 'Ring twice.',
        ], [
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2],
            ['type' => 'test.product', 'ref' => 3],
        ]);
        $orders->updateDetails($order, (new OrderDetails)->managerNote('Regular buyer.'), 'panel');
        $orders->note($order, 'Called, comes on Friday.', 'panel');
        $orders->mark($order, Milestone::Paid, 'api');
        $manager = $this->signIn(['orders.view']);

        $glance = $this->getJson($this->api($order->id).'/glance')->assertOk()->json('data');

        $this->assertSame(['Item A', 2], [$glance['items'][0]['name'], $glance['items'][0]['qty']]);
        $this->assertSame(['lines' => 2, 'units' => 3, 'refunded_units' => 0], $glance['count']);
        $this->assertSame(['name' => 'Alex Doe', 'phone' => '+800 1234 5678', 'email' => 'alex@example.com'], $glance['customer']);
        $this->assertSame(['name' => 'Sam Roe', 'phone' => '+800 1234 5679'], $glance['recipient']);
        $this->assertSame('Ring twice.', $glance['comment']);
        $this->assertSame('Regular buyer.', $glance['manager_note']);
        $this->assertSame('Called, comes on Friday.', $glance['last_note']['body']);
        $this->assertSame(['placed', 'paid'], array_keys($glance['milestones']));
        // The totals as the card's «Summary» has them, from the order already in hand.
        $card = $this->getJson($this->api($order->id))->assertOk()->json('data.order');
        $this->assertSame(array_intersect_key($card['totals'], $glance['totals']), $glance['totals']);
        $this->assertSame(['subtotal', 'adjustments', 'total', 'tenders', 'payable', 'refunded', 'paid', 'paid_first', 'paid_topups', 'balance'], array_keys($glance['totals']));
        $this->assertSame([true, false, 'checkout'], [$glance['paid'], $glance['awaiting_payment'], $glance['source']]);
        $this->assertNotSame($manager->id, $glance['last_note']['author']['id'] ?? null);

        $this->getJson($this->api(999999).'/glance')->assertNotFound();
        $this->signIn(['pages.view']);
        $this->getJson($this->api($order->id).'/glance')->assertForbidden();
    }

    #[Test]
    public function an_opened_row_costs_the_same_however_much_the_order_holds(): void
    {
        $orders = $this->app->make(Orders::class);
        $short = $this->order();
        $long = $this->order([], [['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 3], ['type' => 'test.product', 'ref' => 4]]);

        // Notes by managers, so that the authors are looked up for both: one for the short order,
        // three by three people for the long one.
        $this->signIn();
        $orders->note($short, 'One', 'panel');

        foreach (['One', 'Two', 'Three'] as $word) {
            $this->signIn();
            $orders->note($long, $word, 'panel');
        }

        $this->signIn(['orders.view']);
        // The reader's roles are asked once a request; the first one pays for them.
        $this->getJson($this->api($short->id).'/glance')->assertOk();

        $counts = array_map(
            fn ($order): int => $this->assertNoRepeatedQueries(
                fn () => $this->recordQueries(fn () => $this->getJson($this->api($order->id).'/glance')->assertOk())->count(),
                'A line or a note of an opened row asks a question of its own.',
            ),
            [$short, $long],
        );

        $this->assertSame($counts[0], $counts[1]);
    }

    #[Test]
    public function the_pinned_note_is_the_card_s_own_and_not_the_screen_s(): void
    {
        $order = $this->order();
        $this->signIn();

        $card = $this->getJson($this->api($order->id))->assertOk();
        $this->assertNull($card->json('data.order.manager_note'));
        $this->assertArrayNotHasKey('manager_note', $card->json('data.values'));

        // Pinned in place: the contacts' own write, its journal and its line of the history.
        $pinned = $this->putJson($this->api($order->id).'/details', ['values' => ['manager_note' => 'VIP: call in the morning.']])->assertOk();
        $this->assertSame('VIP: call in the morning.', $pinned->json('data.order.manager_note'));
        $this->assertSame('manager_note', $order->history()->where('kind', StatusChange::DETAILS)->latest('id')->value('note'));

        // The screen's save never sends it back, so it never unpins it by the way.
        $this->putJson($this->api($order->id).'/details', ['values' => [...$card->json('data.values'), 'comment' => 'Ring twice.']])->assertOk();
        $this->assertSame('VIP: call in the morning.', $order->refresh()->manager_note);

        // Emptied, unpinned.
        $this->putJson($this->api($order->id).'/details', ['values' => ['manager_note' => null]])->assertOk();
        $this->assertNull($order->refresh()->manager_note);

        // A reader sees it; writing is `orders.manage`.
        $this->app->make(Orders::class)->updateDetails($order, (new OrderDetails)->managerNote('VIP'), 'api');
        $this->signIn(['orders.view']);
        $this->assertSame('VIP', $this->getJson($this->api($order->id))->json('data.order.manager_note'));
        $this->putJson($this->api($order->id).'/details', ['values' => ['manager_note' => 'Mine']])->assertForbidden();
    }

    #[Test]
    public function the_tab_buyer_has_no_field_of_the_note_any_more(): void
    {
        $this->signIn();
        $screen = (string) json_encode($this->getJson('/api/cms/screens/orders.show')->assertOk()->json('data.root'));

        $this->assertStringNotContainsString('"manager-note"', $screen);
    }
}
