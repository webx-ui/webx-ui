<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * No tool of the orders is listed to, or answers, an administrator who holds no permission, and
 * every permission a tool asks for is one a module declares (WEBX_UI_PERMISSIONS.md §15).
 */
final class AccessCoverageTest extends McpTestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_tool_asks_for_a_declared_permission(): void
    {
        $this->assertToolsNeedPermission('orders');
    }
}
