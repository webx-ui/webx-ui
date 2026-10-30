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
        $this->postJson($this->api($order->id).'/refunds', ['amount' => '1', 'dry_run' => true])->assertForbidden();

        // Money given back is a permission of its own, and it reads the card it writes on.
        $this->signIn(['orders.refund']);
        $this->getJson($this->api($order->id))->assertOk();
        $this->postJson($this->api($order->id).'/cancel', ['reason' => 'x'])->assertForbidden();
    }
}
