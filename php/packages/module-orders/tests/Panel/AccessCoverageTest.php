<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * Nothing of the orders is open to an administrator who holds no permission, and every
 * permission asked for is one a module declares (WEBX_UI_PERMISSIONS.md §15). The buyer's own
 * page and the account come with their stage, each named here with its reason.
 */
final class AccessCoverageTest extends PanelTestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_route_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission(['WebxUi\\Orders\\', 'api/cms/orders']);
    }

    #[Test]
    public function reading_does_not_open_the_writes_and_money_is_apart(): void
    {
        $order = $this->order();

        $this->signIn(['orders.view']);
        $this->getJson($this->api($order->id))->assertOk();
        $this->postJson($this->api($order->id).'/status', ['status_id' => 1, 'dry_run' => true])->assertForbidden();
        $this->postJson($this->api('status'), ['ids' => [$order->id], 'status_id' => 1, 'dry_run' => true])->assertForbidden();
        $this->putJson($this->api($order->id).'/details', ['values' => []])->assertForbidden();
        $this->postJson($this->api($order->id).'/notes', ['body' => 'Called'])->assertForbidden();
        // An edit of the lines is the manager's who takes the order by phone (decision 101).
        $this->postJson($this->api($order->id).'/items/preview', ['revision' => 0])->assertForbidden();
        $this->putJson($this->api($order->id).'/items', ['revision' => 0, 'reason' => 'x'])->assertForbidden();
        $this->postJson($this->api($order->id).'/refunds', ['amount' => '1', 'dry_run' => true])->assertForbidden();

        // Money given back is a permission of its own, and it reads the card it writes on.
        $this->signIn(['orders.refund']);
        $this->getJson($this->api($order->id))->assertOk();
        $this->postJson($this->api($order->id).'/cancel', ['reason' => 'x'])->assertForbidden();
        $this->postJson($this->api($order->id).'/notes', ['body' => 'Called'])->assertForbidden();
    }

    /** A right of another section — the payments, the delivery — opens no entry, no read, no write. */
    #[Test]
    public function another_sections_right_opens_nothing_of_the_orders(): void
    {
        $order = $this->order();

        $this->signIn(['payments.view', 'delivery.view']);

        $allowed = null;

        foreach ((array) $this->getJson('/api/cms/manifest')->assertOk()->json('data.modules') as $module) {
            if (is_array($module) && $module['id'] === 'orders') {
                $allowed = $module['allowed'];
            }
        }

        $this->assertFalse($allowed);
        $this->getJson($this->api())->assertForbidden();
        $this->getJson($this->api($order->id))->assertForbidden();
        $this->getJson($this->api('statuses'))->assertForbidden();
        $this->getJson($this->api('settings'))->assertForbidden();
        $this->postJson($this->api($order->id).'/notes', ['body' => 'Called'])->assertForbidden();
    }
}
