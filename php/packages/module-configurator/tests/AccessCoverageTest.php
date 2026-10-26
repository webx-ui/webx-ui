<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * Every route of the panel's API and every MCP tool of the section asks for a permission the
 * module declares (`configurators.view`, `configurators.manage`).
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_route_and_tool_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission(['WebxUi\\Configurator\\', 'api/cms/configurators']);

        $this->assertToolsNeedPermission('configurators');
    }
}
