<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;
use WebxUi\Catalog\Tests\TestCase;

/**
 * «System → Search index» is open to nobody without a permission of its own, and the permission is
 * declared — by the section, which is there only when the engine is (WEBX_UI_PERMISSIONS.md §15).
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;
    use UsesManticore;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->useManticore($app);
        // A port nobody listens on: the doors are asked, not the server.
        $app['config']->set('webx-catalog-manticore.host', '127.0.0.1');
        $app['config']->set('webx-catalog-manticore.port', 9);
    }

    #[Test]
    public function every_route_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission('api/cms/search-index');
    }
}
