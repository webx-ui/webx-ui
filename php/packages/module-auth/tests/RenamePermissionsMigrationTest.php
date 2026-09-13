<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

final class RenamePermissionsMigrationTest extends TestCase
{
    private const MIGRATION = __DIR__.'/../database/migrations/2026_01_01_000005_rename_users_permissions_to_admins.php';

    #[Test]
    public function a_role_granted_yesterday_still_grants_the_same_thing_today(): void
    {
        $id = DB::table('cms_roles')->insertGetId([
            'slug' => 'editors',
            'name' => 'Editors',
            'permissions' => json_encode(['users.view', 'users.manage', 'media.upload']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->step('up');

        $this->assertSame(['admins.view', 'admins.manage', 'media.upload'], $this->permissions($id));

        $this->step('down');

        $this->assertSame(['users.view', 'users.manage', 'media.upload'], $this->permissions($id));
    }

    #[Test]
    public function a_role_without_the_old_names_is_left_alone(): void
    {
        $id = DB::table('cms_roles')->insertGetId([
            'slug' => 'uploaders',
            'name' => 'Uploaders',
            'permissions' => json_encode(['media.upload']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('cms_roles')->where('id', $id)->value('updated_at');

        $this->step('up');

        $this->assertSame(['media.upload'], $this->permissions($id));
        $this->assertSame($before, DB::table('cms_roles')->where('id', $id)->value('updated_at'));
    }

    /**
     * The file returns an anonymous class, and `Migration` itself declares neither direction —
     * so the step is looked up by name and checked before it is called.
     */
    private function step(string $direction): void
    {
        $migration = require self::MIGRATION;
        $step = [$migration, $direction];

        if (! $migration instanceof Migration || ! is_callable($step)) {
            self::fail("The migration has no {$direction}().");
        }

        $step();
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
