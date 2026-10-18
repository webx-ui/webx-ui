<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * Nothing of the block library, the regions and the content of records is open to an administrator who holds no permission, and every
 * permission asked for is one a module declares (WEBX_UI_PERMISSIONS.md §15).
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_route_and_tool_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission('WebxUi\\Blocks\\', [
            'GET api/cms/blocks/content/{type}/{id}' => 'the content of a record in the panel on the site: asked of the section the record belongs to, inside',
            'POST api/cms/blocks/content/{type}/{id}/edit' => 'the same, written: the record\'s own section decides, inside',
        ]);

        $this->assertToolsNeedPermission('blocks');
    }
}
