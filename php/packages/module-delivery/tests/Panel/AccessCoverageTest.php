<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * Nothing of the section is open to an administrator who holds no permission, and every
 * permission asked for is one a module declares (WEBX_UI_PERMISSIONS.md §15). The step of the
 * checkout (`/api/delivery/*`) is the buyer's and is not a panel route.
 */
final class AccessCoverageTest extends PanelTestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_route_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission(['WebxUi\\Delivery\\', 'api/cms/delivery/']);
    }

    #[Test]
    public function reading_does_not_open_the_writes(): void
    {
        $this->signIn(['delivery.view']);

        $this->getJson($this->api('methods'))->assertOk();
        $this->getJson($this->api('geography/countries'))->assertOk();
        $this->postJson($this->api('geography/countries'), ['code' => 'XX'])->assertForbidden();
        $this->putJson($this->api('methods/order'), ['ids' => []])->assertForbidden();
        $this->putJson($this->api('popular'), ['ids' => []])->assertForbidden();
        $this->postJson($this->api('bindings/batch'), ['action' => 'ignore', 'all' => true])->assertForbidden();
    }
}
