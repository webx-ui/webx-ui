<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * Nothing of «Customers» is open to an administrator who holds no permission, and every
 * permission asked for is one a module declares (WEBX_UI_PERMISSIONS.md §15, spec §16.1).
 */
final class PanelAccessTest extends PanelTestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_route_of_the_panel_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission('WebxUi\\Users\\Http\\Controllers\\Panel\\');
    }
}
