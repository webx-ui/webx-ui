<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Access\DefaultRoles;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\LoginRecord;
use WebxUi\Auth\Models\Role;
use WebxUi\Mcp\Registry\ToolRegistry;

final class AuthMcpToolsTest extends TestCase
{
    private function tool(string $name): callable
    {
        return $this->app->make(ToolRegistry::class)->tool($name)->tool->handler;
    }

    #[Test]
    public function the_module_offers_its_tools_with_read_audit_and_write_scopes(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            [
                // The frame's own first: the journal's two and the cache's reset.
                'history_get',
                'history_runs',
                'cache_reset',
                'admins_list_admins',
                'admins_list_roles',
                'admins_list_permissions',
                'admins_create_role',
                'admins_update_role',
                'admins_sync_default_roles',
                'admins_grant_role',
                'admins_revoke_role',
                'admins_recent_sign_ins',
                'admins_failed_sign_in_bursts',
            ],
            array_map(fn ($tool) => $tool->fullName(), $registry->tools()),
        );

        $this->assertSame(['admins:audit', 'admins:read', 'admins:write', 'cache:write', 'history:read'], $registry->scopes());
    }

    #[Test]
    public function nothing_on_offer_touches_a_password_or_mints_a_token(): void
    {
        $names = array_map(
            fn ($tool) => $tool->fullName(),
            $this->app->make(ToolRegistry::class)->tools(),
        );

        foreach (['password', 'token', 'create_user', 'delete_user'] as $forbidden) {
            $this->assertEmpty(
                array_filter($names, static fn (string $name): bool => str_contains($name, $forbidden)),
                "A tool touching [{$forbidden}] is on offer to agents.",
            );
        }
    }

    #[Test]
    public function it_lists_administrators_with_their_roles(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['pages.view']));

        $rows = ($this->tool('admins_list_admins'))([]);

        $this->assertCount(1, $rows);
        $this->assertSame('admin@example.test', $rows[0]['email']);
        $this->assertSame(['editor'], $rows[0]['roles']);
    }

    #[Test]
    public function granting_a_role_can_be_asked_about_before_it_happens(): void
    {
        $admin = $this->admin();
        $this->role('editor', ['pages.view']);

        $preview = ($this->tool('admins_grant_role'))([
            'email' => 'admin@example.test',
            'role' => 'editor',
            'dry_run' => true,
        ]);

        $this->assertTrue($preview['would_change']);
        $this->assertFalse($preview['applied']);
        $this->assertSame(0, $admin->roles()->count());

        $applied = ($this->tool('admins_grant_role'))([
            'email' => 'admin@example.test',
            'role' => 'editor',
        ]);

        $this->assertTrue($applied['applied']);
        $this->assertSame(1, $admin->roles()->count());
    }

    #[Test]
    public function revoking_is_the_same_in_reverse(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['pages.view']));

        ($this->tool('admins_revoke_role'))(['email' => 'admin@example.test', 'role' => 'editor']);

        $this->assertSame(0, $admin->roles()->count());
    }

    #[Test]
    public function a_role_change_that_would_do_nothing_says_so(): void
    {
        $this->admin();
        $this->role('editor', ['pages.view']);

        $result = ($this->tool('admins_revoke_role'))([
            'email' => 'admin@example.test',
            'role' => 'editor',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['would_change']);
    }

    #[Test]
    public function an_unknown_address_or_role_is_reported_rather_than_thrown(): void
    {
        $this->role('editor', ['pages.view']);

        $this->assertFalse(($this->tool('admins_grant_role'))([
            'email' => 'ghost@example.test',
            'role' => 'editor',
        ])['ok']);

        $this->admin();

        $this->assertFalse(($this->tool('admins_grant_role'))([
            'email' => 'admin@example.test',
            'role' => 'nope',
        ])['ok']);
    }

    #[Test]
    public function it_finds_bursts_of_failed_sign_ins(): void
    {
        foreach (range(1, 6) as $i) {
            LoginRecord::query()->create([
                'email' => 'target@example.test',
                'successful' => false,
                'ip' => '10.0.0.1',
                'created_at' => Carbon::now()->subMinutes($i),
            ]);
        }

        LoginRecord::query()->create([
            'email' => 'quiet@example.test',
            'successful' => false,
            'created_at' => Carbon::now(),
        ]);

        // Old noise must not count towards the window.
        LoginRecord::query()->create([
            'email' => 'target@example.test',
            'successful' => false,
            'created_at' => Carbon::now()->subDays(30),
        ]);

        $bursts = ($this->tool('admins_failed_sign_in_bursts'))([]);

        $this->assertCount(1, $bursts);
        $this->assertSame('target@example.test', $bursts[0]['email']);
        $this->assertSame(6, $bursts[0]['attempts']);
    }

    #[Test]
    public function recent_sign_ins_are_capped(): void
    {
        foreach (range(1, 5) as $i) {
            LoginRecord::query()->create([
                'email' => "person{$i}@example.test",
                'successful' => true,
                'created_at' => Carbon::now()->subMinutes($i),
            ]);
        }

        $this->assertCount(2, ($this->tool('admins_recent_sign_ins'))(['limit' => 2]));
        $this->assertCount(5, ($this->tool('admins_recent_sign_ins'))([]));
    }

    #[Test]
    public function the_roles_are_listed_with_what_they_grant_and_who_holds_them(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['pages.view', 'pages.manage']));
        $this->role('empty', []);

        $rows = ($this->tool('admins_list_roles'))([]);

        $this->assertSame(
            [
                ['slug' => 'editor', 'name' => 'Editor', 'permissions' => ['pages.view', 'pages.manage'], 'users' => ['admin@example.test']],
                ['slug' => 'empty', 'name' => 'Empty', 'permissions' => [], 'users' => []],
            ],
            $rows,
        );

        // Whoever reads roles in the panel reads them here.
        $this->assertSame(
            ['admins.view', 'admins.manage', 'roles.manage', 'roles.assign'],
            $this->app->make(ToolRegistry::class)->tool('admins_list_roles')->permissions(),
        );
    }

    #[Test]
    public function the_permissions_on_offer_say_which_the_caller_holds(): void
    {
        $manager = $this->manager(['roles.manage', 'admins.view']);

        /** @var list<array{module: string, permissions: list<array{name: string, held: bool}>}> $matrix */
        $matrix = ($this->tool('admins_list_permissions'))([], $manager);
        $admins = array_values(array_filter($matrix, static fn (array $row): bool => $row['module'] === 'admins'))[0];

        $held = array_column($admins['permissions'], 'held', 'name');
        $this->assertTrue($held['roles.manage']);
        $this->assertFalse($held['admins.manage']);
    }

    #[Test]
    public function a_role_is_made_after_a_dry_run_that_says_what_it_would_grant(): void
    {
        $manager = $this->manager(['roles.manage', 'admins.view']);

        $preview = ($this->tool('admins_create_role'))(['name' => 'People readers', 'permissions' => ['admins.view'], 'dry_run' => true], $manager);

        $this->assertTrue($preview['ok']);
        $this->assertFalse($preview['applied']);
        $this->assertSame('people-readers', $preview['role']);
        $this->assertSame(['admins.view'], $preview['gained']);
        $this->assertFalse(Role::query()->where('slug', 'people-readers')->exists());

        $made = ($this->tool('admins_create_role'))(['name' => 'People readers', 'permissions' => ['admins.view']], $manager);

        $this->assertTrue($made['applied']);
        $this->assertSame(['admins.view'], Role::query()->where('slug', 'people-readers')->sole()->permissions);

        // The same name again gets a code of its own.
        $this->assertSame('people-readers-2', ($this->tool('admins_create_role'))(['name' => 'People readers', 'permissions' => []], $manager)['role']);
    }

    #[Test]
    public function a_role_is_edited_whole_or_by_what_it_gains_and_loses(): void
    {
        $role = $this->role('auditors', ['admins.view', 'admins.audit']);

        $result = ($this->tool('admins_update_role'))(['role' => 'auditors', 'add' => ['admins.manage'], 'remove' => ['admins.audit']]);

        $this->assertTrue($result['applied']);
        $this->assertSame(['admins.manage'], $result['gained']);
        $this->assertSame(['admins.audit'], $result['lost']);
        $this->assertSame(['admins.view', 'admins.manage'], $role->refresh()->permissions);

        ($this->tool('admins_update_role'))(['role' => 'auditors', 'name' => 'Auditors', 'permissions' => ['admins.audit']]);
        $this->assertSame(['Auditors', ['admins.audit']], [$role->refresh()->name, $role->permissions]);

        $same = ($this->tool('admins_update_role'))(['role' => 'auditors', 'permissions' => ['admins.audit']]);
        $this->assertFalse($same['would_change']);

        $this->assertFalse(($this->tool('admins_update_role'))(['role' => 'nope', 'name' => 'x'])['ok']);
    }

    #[Test]
    public function a_permission_no_module_declares_is_refused_as_in_the_form(): void
    {
        $this->expectException(ValidationException::class);

        ($this->tool('admins_create_role'))(['name' => 'Typos', 'permissions' => ['pages.mange']]);
    }

    #[Test]
    public function a_role_gains_only_what_its_editor_holds_even_in_a_dry_run(): void
    {
        $manager = $this->manager(['roles.manage', 'admins.view']);

        // Rule 2: nothing handed out that the editor does not hold.
        foreach ([true, false] as $dryRun) {
            $refused = ($this->tool('admins_create_role'))(['name' => 'Wider', 'permissions' => ['admins.manage'], 'dry_run' => $dryRun], $manager);

            $this->assertFalse($refused['ok']);
            $this->assertSame('beyond-yours', $refused['rule']);
            $this->assertSame(['admins.manage'], $refused['missing']);
        }

        $this->assertFalse(Role::query()->where('name', 'Wider')->exists());

        $other = $this->role('others', ['admins.audit']);
        $this->assertSame('beyond-yours', ($this->tool('admins_update_role'))(['role' => 'others', 'add' => ['admins.manage']], $manager)['rule']);

        // Taking away what the editor does not hold hands nothing out.
        $this->assertTrue(($this->tool('admins_update_role'))(['role' => 'others', 'remove' => ['admins.audit']], $manager)['applied']);
        $this->assertSame([], $other->refresh()->permissions);
    }

    #[Test]
    public function a_role_the_editor_holds_is_not_theirs_to_edit(): void
    {
        $manager = $this->manager(['roles.manage', 'admins.view']);
        $own = $manager->roles()->sole();

        // Rule 5: the way to widen yourself by the back door.
        $refused = ($this->tool('admins_update_role'))(['role' => $own->slug, 'remove' => ['admins.view']], $manager);

        $this->assertFalse($refused['ok']);
        $this->assertSame('role-you-hold', $refused['rule']);
        $this->assertSame(['roles.manage', 'admins.view'], $own->refresh()->permissions);
    }

    #[Test]
    public function without_roles_manage_nothing_is_made_or_edited(): void
    {
        $assigner = $this->manager(['roles.assign', 'admins.view']);

        $this->assertSame(['roles.manage'], $this->app->make(ToolRegistry::class)->tool('admins_create_role')->permissions());
        $this->assertSame(['roles.manage'], $this->app->make(ToolRegistry::class)->tool('admins_update_role')->permissions());

        // Should a client call the handler past the door, the rules still say no.
        $this->assertSame('forbidden', ($this->tool('admins_create_role'))(['name' => 'Sneaky', 'permissions' => []], $assigner)['rule']);
        $this->assertFalse(Role::query()->where('name', 'Sneaky')->exists());
    }

    #[Test]
    public function the_ready_made_roles_are_brought_up_after_a_dry_run_that_says_how(): void
    {
        $this->app->make(DefaultRoles::class)->seed();
        $developer = Role::query()->where('slug', 'developer')->firstOrFail();
        $developer->permissions = ['cache.view'];
        $developer->save();
        Role::query()->where('slug', 'viewer')->delete();

        $this->assertSame(['roles.manage'], $this->app->make(ToolRegistry::class)->tool('admins_sync_default_roles')->permissions());

        $dry = ($this->tool('admins_sync_default_roles'))(['dry_run' => true]);

        $this->assertTrue($dry['ok']);
        $this->assertTrue($dry['would_change']);
        $this->assertFalse($dry['applied']);
        $this->assertSame(['viewer' => ['cache.view']], $dry['created']);
        $this->assertSame([], $dry['added']);
        $this->assertFalse(Role::query()->where('slug', 'viewer')->exists());

        // Without creating, there is nothing to do: the reset was taken away and stays away.
        $this->assertFalse(($this->tool('admins_sync_default_roles'))(['create' => false])['would_change']);

        $done = ($this->tool('admins_sync_default_roles'))([]);

        $this->assertTrue($done['applied']);
        $this->assertTrue(Role::query()->where('slug', 'viewer')->exists());
        $this->assertSame(['cache.view'], $developer->refresh()->permissions);
    }

    #[Test]
    public function the_ready_made_roles_gain_only_what_the_caller_holds(): void
    {
        $manager = $this->manager(['roles.manage', 'admins.view', 'cache.view']);

        foreach ([true, false] as $dryRun) {
            $answer = ($this->tool('admins_sync_default_roles'))(['dry_run' => $dryRun], $manager);

            // The viewer is made of what the manager holds; the developer would hand out the reset.
            $this->assertFalse($answer['ok']);
            $this->assertSame(['viewer' => ['cache.view']], $answer['created']);
            $this->assertSame('developer', $answer['refused'][0]['role']);
            $this->assertSame('beyond-yours', $answer['refused'][0]['rule']);
        }

        $this->assertFalse(Role::query()->where('slug', 'developer')->exists());
        $this->assertTrue(Role::query()->where('slug', 'viewer')->exists());
    }

    /**
     * @param  list<string>  $permissions
     */
    private function manager(array $permissions): CmsUser
    {
        $manager = $this->admin('manager@example.test');
        $manager->roles()->attach($this->role('managers', $permissions));

        return $manager;
    }
}
