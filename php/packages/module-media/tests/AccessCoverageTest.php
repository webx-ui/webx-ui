<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;
use WebxUi\Mcp\McpServiceProvider;

/**
 * Nothing of the media library is open to an administrator who holds no permission, and every
 * permission asked for is one a module declares (WEBX_UI_PERMISSIONS.md §15).
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;
    use RefreshDatabase;

    /**
     * The MCP server too: the library's tools are what an agent reaches it through.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class];
    }

    #[Test]
    public function every_route_and_tool_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission('WebxUi\\Media\\');

        $this->assertToolsNeedPermission('media');
    }
}
