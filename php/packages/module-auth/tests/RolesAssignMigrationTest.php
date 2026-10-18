<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Assigning roles became a permission of its own, and nobody who did it yesterday lost it
 * (WEBX_UI_PERMISSIONS.md §13, point 2).
 */
final class RolesAssignMigrationTest extends TestCase
{
    private const MIGRATION = __DIR__.'/../database/migrations/2026_10_18_000000_grant_roles_assign_to_admin_managers.php';

    #[Test]
    public function a_role_that_managed_administrators_still_assigns_roles(): void
    {
        $managers = $this->insert('managers', ['admins.view', 'admins.manage']);
        $editors = $this->insert('editors', ['pages.view', 'pages.manage']);
        $already = $this->insert('owners', ['admins.manage', 'roles.assign']);

        $this->up();

        $this->assertSame(['admins.view', 'admins.manage', 'roles.assign'], $this->permissions($managers));
        $this->assertSame(['pages.view', 'pages.manage'], $this->permissions($editors));
        $this->assertSame(['admins.manage', 'roles.assign'], $this->permissions($already));

        // Nobody is given the editing of roles: it could not be done from the panel before.
        foreach ([$managers, $editors, $already] as $id) {
            $this->assertNotContains('roles.manage', $this->permissions($id));
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function insert(string $slug, array $permissions): int
    {
        return DB::table('cms_roles')->insertGetId([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function up(): void
    {
        $migration = require self::MIGRATION;

        if (! $migration instanceof Migration || ! method_exists($migration, 'up')) {
            self::fail('The migration has no up().');
        }

        $migration->up();
    }

    /**
     * @return list<string>
     */
    private function permissions(int $id): array
    {
        /** @var list<string> $permissions */
        $permissions = json_decode((string) DB::table('cms_roles')->where('id', $id)->value('permissions'), true);

        return $permissions;
    }
}
