<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * Nothing of the configurators is open to an administrator who holds no permission, and every
 * permission asked for is one the module declares (WEBX_UI_PERMISSIONS.md §15).
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_tool_asks_for_a_declared_permission(): void
    {
        $this->assertToolsNeedPermission('configurators');
    }
}
