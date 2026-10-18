<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * Nothing about other people's accounts, roles or keys is open to an administrator who holds no
 * permission; what is open is about themselves (WEBX_UI_PERMISSIONS.md §5.5).
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_route_and_tool_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission('WebxUi\\Auth\\', [
            'GET api/cms/auth/me' => 'yourself',
            'PUT api/cms/auth/me' => 'yourself: the profile',
            'PUT api/cms/auth/locale' => 'yourself: the language of the panel',
            'PUT api/cms/auth/theme' => 'yourself: light or dark',
            'PUT api/cms/auth/site-bar' => 'yourself: the panel on the site',
            'POST api/cms/auth/logout' => 'yourself',
            'GET api/cms/auth/connections' => 'your own agents; somebody else\'s are asked for admins.manage inside',
            'DELETE api/cms/auth/connections/{connection}' => 'your own agent; somebody else\'s are asked for admins.manage inside',
            'GET api/cms/auth/keys' => 'your own keys; everybody\'s with admins.manage, asked inside',
            'GET api/cms/auth/keys/scopes' => 'what your own key may carry; somebody else\'s is asked for admins.manage inside',
            'POST api/cms/auth/keys' => 'a key for yourself; for somebody else admins.manage and the rules, asked inside',
            'DELETE api/cms/auth/keys/{key}' => 'your own key; somebody else\'s is asked for admins.manage inside',
        ]);

        $this->assertToolsNeedPermission('admins');
    }
}
