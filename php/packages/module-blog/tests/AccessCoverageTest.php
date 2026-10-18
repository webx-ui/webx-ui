<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * Nothing of articles, rubrics, tags and authors is open to an administrator who holds no
 * permission, and every permission asked for is one a module declares (WEBX_UI_PERMISSIONS.md §15).
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    /**
     * Authors on: the section and the permission it declares are there only when a site has them,
     * and its routes are there either way.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-blog.authors', true);
    }

    #[Test]
    public function every_route_and_tool_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission(['WebxUi\\Blog\\', 'api/cms/blog/']);

        $this->assertToolsNeedPermission('articles', 'rubrics', 'tags');
    }
}
