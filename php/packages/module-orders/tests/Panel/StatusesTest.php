<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Orders;

/**
 * The screen of the statuses (spec §5.6, §11.4): both levels with the orders in each, the order
 * and the binding dragged, a status deleted softly with the consequences said first, and brought
 * back. What it refuses is what would leave an order nowhere to go.
 */
final class StatusesTest extends PanelTestCase
{
    private function named(string $name): OrderStatus
    {
        return OrderStatus::withTrashed()->get()->firstOrFail(static fn (OrderStatus $status): bool => $status->getTranslation('name', 'en') === $name);
    }

    private function group(string $code): PublicStatus
    {
        return PublicStatus::withTrashed()->where('code', $code)->firstOrFail();
    }

    private function statuses(string $path = ''): string
    {
        return $this->api('statuses'.$path);
    }

    /** An order standing in the status named. */
    private function orderIn(string $name): void
    {
        $order = $this->order();
        $this->app->make(Orders::class)->moveTo($order, $this->named($name), 'api');
    }

    #[Test]
    public function the_board_has_both_levels_with_the_orders_in_each(): void
    {
        $this->orderIn('Being packed');
        $this->orderIn('Being packed');
        $this->signIn(['orders.statuses']);

        $board = $this->getJson($this->statuses())->assertOk()->json('data');

        $this->assertSame(['accepted', 'in_progress', 'shipped', 'completed', 'cancelled'], array_column($board['groups'], 'code'));
        $progress = self::pick($board['groups'], 'code', 'in_progress');
        $this->assertSame(['Taken into work', 'Waiting for a call', 'Being packed'], array_column($progress['statuses'], 'label'));
        $this->assertSame(2, self::pick($progress['statuses'], 'label', 'Being packed')['orders']);
        $this->assertSame(2, $progress['orders']);
        // Every language of the site, for the dialog to edit.
        $this->assertSame(['en', 'ru', 'kk'], array_keys($progress['name']));
        $this->assertSame([$this->named('New')->id, $this->named('Awaiting payment')->id], $board['initial']);
        $this->assertSame([], $board['deleted']['statuses']);
        $this->assertSame(['paid', 'received', 'cancelled'], array_column($board['milestones'], 'value'));
    }

    #[Test]
    public function the_status_new_orders_come_into_is_not_deleted_or_switched_off(): void
    {
        $this->signIn(['orders.statuses']);
        $new = $this->named('New');

        $preview = $this->deleteJson($this->statuses('/internal/'.$new->id), ['dry_run' => true])->assertOk()->json('data');
        $this->assertSame('initial', $preview['refusal']);
        $this->assertStringContainsString('settings', (string) $preview['message']);

        $this->deleteJson($this->statuses('/internal/'.$new->id))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson($this->statuses('/internal/'.$new->id), [
            'public_status_id' => $new->public_status_id, 'name' => ['en' => 'New'], 'color' => 'info', 'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertFalse($new->refresh()->trashed());
        $this->assertTrue($new->is_active);
    }

    #[Test]
    public function the_last_active_status_of_a_group_with_orders_stays(): void
    {
        $this->signIn(['orders.statuses']);
        $handed = $this->named('Handed to delivery');

        // An empty group may lose its only stage.
        $this->deleteJson($this->statuses('/internal/'.$handed->id), ['dry_run' => true])->assertOk()->assertJsonPath('data.refusal', null);

        $this->orderIn('Handed to delivery');
        $this->deleteJson($this->statuses('/internal/'.$handed->id), ['dry_run' => true])->assertOk()->assertJsonPath('data.refusal', 'last_active');
        $this->deleteJson($this->statuses('/internal/'.$handed->id))->assertUnprocessable()->assertJsonValidationErrors('status');

        // Another active stage in the group, and it may go — its orders stay in it.
        $this->postJson($this->statuses('/internal'), [
            'public_status_id' => $handed->public_status_id, 'name' => ['en' => 'At the pickup point'], 'color' => 'info',
        ])->assertCreated();

        $preview = $this->deleteJson($this->statuses('/internal/'.$handed->id), ['dry_run' => true])->assertOk()->json('data');
        $this->assertNull($preview['refusal']);
        $this->assertSame(1, $preview['orders']);
        $this->assertSame([
            'Orders in «Handed to delivery»: 1.',
            'They stay in it: the list marks the status «deleted», and no order is moved into it any more. Buyers keep seeing «Shipped».',
            'You can bring the status back under «Deleted» at the bottom of this page.',
            'No messages are sent.',
        ], $preview['lines']);

        $board = $this->deleteJson($this->statuses('/internal/'.$handed->id))->assertOk()->json('data');
        $this->assertTrue($handed->refresh()->trashed());
        $this->assertSame(['Handed to delivery'], array_column($board['deleted']['statuses'], 'label'));
        $this->assertSame(1, $board['deleted']['statuses'][0]['orders']);
    }

    #[Test]
    public function a_status_whose_group_is_deleted_comes_back_only_after_it(): void
    {
        $this->signIn(['orders.statuses']);
        $handed = $this->named('Handed to delivery');
        $shipped = $this->group('shipped');

        $this->deleteJson($this->statuses('/internal/'.$handed->id))->assertOk();
        $this->deleteJson($this->statuses('/public/'.$shipped->id))->assertOk();
        $this->assertTrue($shipped->refresh()->trashed());

        $this->postJson($this->statuses('/internal/'.$handed->id.'/restore'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Bring «Shipped» back first, or choose another public status.');
        $this->assertTrue($handed->refresh()->trashed());

        // Bound to a live group while still deleted, it may come back there.
        $this->putJson($this->statuses('/internal/'.$handed->id), [
            'public_status_id' => $this->group('in_progress')->id, 'name' => ['en' => 'Handed to delivery'], 'color' => 'info',
        ])->assertOk();
        $this->postJson($this->statuses('/internal/'.$handed->id.'/restore'))->assertOk();
        $this->assertFalse($handed->refresh()->trashed());

        $this->postJson($this->statuses('/public/'.$shipped->id.'/restore'))->assertOk();
        $this->assertFalse($shipped->refresh()->trashed());
    }

    #[Test]
    public function a_public_group_goes_only_empty_or_with_its_stages_moved(): void
    {
        $this->orderIn('Being packed');
        $this->signIn(['orders.statuses']);
        $progress = $this->group('in_progress');
        $accepted = $this->group('accepted');

        $preview = $this->deleteJson($this->statuses('/public/'.$progress->id), ['dry_run' => true])->assertOk()->json('data');
        $this->assertSame('has_active', $preview['refusal']);
        $this->assertSame(['Taken into work', 'Waiting for a call', 'Being packed'], array_column($preview['active'], 'name'));
        $this->deleteJson($this->statuses('/public/'.$progress->id))->assertUnprocessable()->assertJsonValidationErrors('status');

        $preview = $this->deleteJson($this->statuses('/public/'.$progress->id), ['move_to' => $accepted->id, 'dry_run' => true])->assertOk()->json('data');
        $this->assertNull($preview['refusal']);
        $this->assertSame([0, 0, 1], array_column($preview['rebound'], 'orders'));

        $this->deleteJson($this->statuses('/public/'.$progress->id), ['move_to' => $accepted->id])->assertOk();
        $this->assertTrue($progress->refresh()->trashed());
        $this->assertSame($accepted->id, $this->named('Being packed')->public_status_id);
    }

    #[Test]
    public function dragging_reorders_and_rebinds_with_the_buyers_counted_first(): void
    {
        $this->orderIn('Waiting for a call');
        $this->signIn(['orders.statuses']);
        $board = $this->getJson($this->statuses())->assertOk()->json('data');
        $publics = array_column($board['groups'], 'id');
        $groups = [];

        foreach ($board['groups'] as $group) {
            $groups[$group['id']] = array_column($group['statuses'], 'id');
        }

        // «Waiting for a call» dragged from «In progress» to the top of «Accepted», and the two
        // last groups swapped.
        $call = $this->named('Waiting for a call')->id;
        $accepted = $this->group('accepted')->id;
        $progress = $this->group('in_progress')->id;
        $groups[$progress] = array_values(array_diff($groups[$progress], [$call]));
        array_unshift($groups[$accepted], $call);
        [$publics[3], $publics[4]] = [$publics[4], $publics[3]];

        $preview = $this->putJson($this->statuses('/arrange'), ['publics' => $publics, 'groups' => $groups, 'dry_run' => true])->assertOk()->json('data');
        $this->assertSame([[
            'id' => $call, 'from' => $progress, 'to' => $accepted, 'orders' => 1,
            'name' => 'Waiting for a call', 'from_name' => 'In progress', 'to_name' => 'Accepted',
        ]], $preview['rebound']);
        $this->assertSame($progress, $this->named('Waiting for a call')->public_status_id, 'A dry run moved the status.');

        $after = $this->putJson($this->statuses('/arrange'), ['publics' => $publics, 'groups' => $groups])->assertOk()->json('data');
        $this->assertSame(['accepted', 'in_progress', 'shipped', 'cancelled', 'completed'], array_column($after['groups'], 'code'));
        $this->assertSame(['Waiting for a call', 'New', 'Awaiting payment'], array_column(self::pick($after['groups'], 'code', 'accepted')['statuses'], 'label'));

        // A list missing a status is not an arrangement.
        $groups[$accepted] = [];
        $this->putJson($this->statuses('/arrange'), ['publics' => $publics, 'groups' => $groups])->assertUnprocessable()->assertJsonValidationErrors('groups');
    }

    #[Test]
    public function dragging_the_last_active_stage_out_of_a_group_with_orders_is_refused(): void
    {
        $this->signIn(['orders.statuses']);
        $shipped = $this->group('shipped')->id;

        // An order left in a switched-off stage of «Shipped»: it goes nowhere when «Handed to
        // delivery» is dragged out, and would have no active stage left to go to.
        $this->postJson($this->statuses('/internal'), ['public_status_id' => $shipped, 'name' => ['en' => 'Lost in transit'], 'color' => 'danger'])->assertCreated();
        $this->orderIn('Lost in transit');
        $lost = $this->named('Lost in transit');
        $this->putJson($this->statuses('/internal/'.$lost->id), ['public_status_id' => $shipped, 'name' => ['en' => 'Lost in transit'], 'color' => 'danger', 'is_active' => false])->assertOk();

        $board = $this->getJson($this->statuses())->assertOk()->json('data');
        $groups = [];

        foreach ($board['groups'] as $group) {
            $groups[$group['id']] = array_column($group['statuses'], 'id');
        }

        $groups[$this->group('completed')->id][] = $this->named('Handed to delivery')->id;
        $groups[$shipped] = [$lost->id];

        $this->putJson($this->statuses('/arrange'), ['publics' => array_column($board['groups'], 'id'), 'groups' => $groups])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame($shipped, $this->named('Handed to delivery')->public_status_id);

        // Switching off its last active stage is refused the same way.
        $handed = $this->named('Handed to delivery');
        $this->putJson($this->statuses('/internal/'.$handed->id), ['public_status_id' => $shipped, 'name' => ['en' => 'Handed to delivery'], 'color' => 'info', 'is_active' => false, 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.refusal', 'last_active');
    }

    #[Test]
    public function statuses_are_written_with_their_names_by_language(): void
    {
        $this->signIn(['orders.statuses']);

        $this->postJson($this->statuses('/public'), ['code' => 'Bad Code', 'name' => ['en' => 'On hold']])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson($this->statuses('/public'), ['code' => 'in_progress', 'name' => ['en' => 'On hold']])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson($this->statuses('/public'), ['code' => 'on_hold', 'name' => ['ru' => 'Отложен']])->assertUnprocessable()->assertJsonValidationErrors('name.en');

        $board = $this->postJson($this->statuses('/public'), [
            'code' => 'on_hold', 'name' => ['en' => 'On hold', 'ru' => 'Отложен', 'kk' => ''], 'description' => ['en' => 'We will call you.'],
        ])->assertCreated()->json('data');
        $hold = self::pick($board['groups'], 'code', 'on_hold');
        $this->assertSame(['en' => 'On hold', 'ru' => 'Отложен'], $hold['name']);
        $this->assertSame('on_hold', end($board['groups'])['code']);

        $this->postJson($this->statuses('/internal'), [
            'public_status_id' => $hold['id'], 'name' => ['en' => 'Waiting for stock'], 'color' => 'pink',
        ])->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->postJson($this->statuses('/internal'), [
            'public_status_id' => $hold['id'], 'name' => ['en' => 'Waiting for stock'], 'color' => 'warning', 'sets_milestone' => 'refunded',
        ])->assertUnprocessable()->assertJsonValidationErrors('sets_milestone');

        $board = $this->postJson($this->statuses('/internal'), [
            'public_status_id' => $hold['id'], 'name' => ['en' => 'Waiting for stock'], 'color' => 'warning', 'sets_milestone' => null,
        ])->assertCreated()->json('data');
        $stock = self::pick($board['groups'], 'code', 'on_hold')['statuses'][0];
        $this->assertSame(['Waiting for stock', 'warning', null, true], [$stock['label'], $stock['color'], $stock['milestone'], $stock['active']]);
    }

    #[Test]
    public function only_the_statuses_permission_opens_the_screen(): void
    {
        $this->signIn(['orders.view', 'orders.manage', 'orders.refund']);

        $this->getJson($this->statuses())->assertForbidden();
        $this->putJson($this->statuses('/arrange'), ['publics' => [], 'groups' => []])->assertForbidden();
        $this->getJson($this->api('settings'))->assertForbidden();
    }

    #[Test]
    public function without_the_settings_module_the_config_decides(): void
    {
        $this->signIn(['orders.statuses']);

        $this->getJson($this->api('settings'))->assertOk()->assertJsonPath('data.stored', false)->assertJsonPath('data.sample', '1001');
        $this->putJson($this->api('settings'), ['values' => ['orders.number.prefix' => 'A-']])->assertNotFound();
    }
}
