<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * Nothing of address rules, redirects and the rest of SEO is open to an administrator who holds no permission, and every
 * permission asked for is one a module declares (WEBX_UI_PERMISSIONS.md §15).
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_route_and_tool_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission('WebxUi\\Seo\\', [
            'GET api/cms/seo/site-bar' => 'the SEO card of a record in the panel on the site: asked of the record\'s own section, inside',
            'PUT api/cms/seo/entity/{type}/{id}' => 'the same, written: the record\'s own section decides, inside',
        ]);

        $this->assertToolsNeedPermission('seo');
    }
}
