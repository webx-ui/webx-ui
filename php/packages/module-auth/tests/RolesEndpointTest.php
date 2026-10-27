<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Auth\Access\Refusal;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;

/**
 * The screen «Roles» on the server (WEBX_UI_PERMISSIONS.md §11): the list, the matrix of
 * permissions, and making, editing and deleting a role under rules 2 and 5.
 *
 * Only the modules of this package are installed here, so the permissions a role may list are
 * theirs: `admins.*`, `roles.*`, `cache.*`.
 */
final class RolesEndpointTest extends TestCase
{
    #[Test]
    public function the_list_says_how_many_hold_each_role_and_which_the_reader_holds(): void
    {
        $reader = $this->role('reader', ['admins.view']);
        $other = $this->role('other', ['cache.view']);
        $me = $this->admin('me@example.test');
        $me->roles()->attach($reader);
        $this->admin('anna@example.test')->roles()->attach($other);

        $roles = $this->keyed($this->actingAs($me, 'cms')->getJson('/api/cms/auth/roles')->assertOk()->json('data'), 'slug');

        $this->assertTrue($roles['reader']['held']);
        $this->assertFalse($roles['other']['held']);
        $this->assertSame(1, $roles['other']['users']);

        $this->getJson("/api/cms/auth/roles/{$other->id}")
            ->assertOk()
            ->assertJsonPath('data.holders.0.email', 'anna@example.test');
    }

    #[Test]
    public function the_matrix_lists_every_declared_permission_by_module_and_what_the_reader_holds(): void
    {
        $editor = $this->editor(['cache.view']);

        $rows = $this->actingAs($editor, 'cms')->matrix();

        $admins = $rows['admins'];
        $this->assertSame(['admins.view', 'admins.manage', 'admins.audit', 'roles.manage', 'roles.assign'], array_keys($admins));
        $this->assertSame('Assign roles', $admins['roles.assign']['label']);
        $this->assertTrue($admins['roles.manage']['held']);
        $this->assertFalse($admins['admins.manage']['held']);

        $cache = $rows['cache'];
        $this->assertSame('View', $cache['cache.view']['label']);
        $this->assertTrue($cache['cache.view']['held']);
        $this->assertFalse($cache['cache.reset']['held']);
    }

    #[Test]
    public function a_role_is_made_of_what_its_editor_holds(): void
    {
        $editor = $this->editor(['cache.view']);

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/auth/roles', ['name' => 'Cache readers', 'permissions' => ['cache.view']])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'cache-readers')
            ->assertJsonPath('data.permissions', ['cache.view']);

        // Rule 2: nothing the editor does not hold.
        $this->postJson('/api/cms/auth/roles', ['name' => 'Resetters', 'permissions' => ['cache.reset']])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::BEYOND_YOURS)
            ->assertJsonPath('missing', ['cache.reset']);

        // A permission no module declares is a typo, not a permission.
        $this->postJson('/api/cms/auth/roles', ['name' => 'Typo', 'permissions' => ['cache.veiw']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions.0');

        // A code is unique, and made unique when it is made from the name.
        $this->postJson('/api/cms/auth/roles', ['name' => 'Cache readers', 'permissions' => []])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'cache-readers-2');
        $this->postJson('/api/cms/auth/roles', ['name' => 'X', 'slug' => 'cache-readers', 'permissions' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame(['editors', 'cache-readers', 'cache-readers-2'], Role::query()->orderBy('id')->pluck('slug')->all());
    }

    #[Test]
    public function a_role_is_edited_within_the_rules(): void
    {
        $editor = $this->editor(['cache.view']);
        $role = $this->role('readers', ['cache.view', 'cache.reset']);

        // Taking away what you do not hold yourself is not handing it out.
        $this->actingAs($editor, 'cms')
            ->putJson("/api/cms/auth/roles/{$role->id}", ['name' => 'Readers', 'permissions' => ['cache.view']])
            ->assertOk()
            ->assertJsonPath('data.name', 'Readers')
            ->assertJsonPath('data.permissions', ['cache.view']);

        // Giving it back is.
        $this->putJson("/api/cms/auth/roles/{$role->id}", ['permissions' => ['cache.view', 'cache.reset']])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::BEYOND_YOURS);

        // Rule 5: the role the editor holds is somebody else's to edit, or to delete.
        $own = $editor->roles()->firstOrFail();
        $this->putJson("/api/cms/auth/roles/{$own->id}", ['name' => 'Mine'])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::ROLE_YOU_HOLD);
        $this->deleteJson("/api/cms/auth/roles/{$own->id}")
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::ROLE_YOU_HOLD);

        // A permission left over from a module taken off the site does not stop a save.
        Role::query()->whereKey($role->id)->update(['permissions' => json_encode(['cache.view', 'gone.view'])]);
        $this->putJson("/api/cms/auth/roles/{$role->id}", ['name' => 'Still', 'permissions' => ['cache.view', 'gone.view']])
            ->assertOk();

        $this->assertTrue(HistoryEntry::query()
            ->where('subject_type', Role::TYPE)
            ->where('subject_id', $role->id)
            ->where('event', HistoryEntry::REFUSED)
            ->exists());
    }

    #[Test]
    public function deleting_a_role_takes_it_from_its_holders_and_says_so_on_each(): void
    {
        $editor = $this->editor([]);
        $role = $this->role('readers', ['cache.view']);
        $anna = $this->admin('anna@example.test');
        $anna->roles()->attach($role);

        $this->actingAs($editor, 'cms')->deleteJson("/api/cms/auth/roles/{$role->id}")->assertOk();

        $this->assertNull(Role::query()->find($role->id));
        $this->assertSame([], $anna->roles()->pluck('slug')->all());

        $change = collect(HistoryEntry::query()
            ->where('subject_type', CmsUser::TYPE)
            ->where('subject_id', $anna->id)
            ->where('event', HistoryEntry::UPDATED)
            ->sole()
            ->changes)->firstWhere('field', 'roles');

        $this->assertSame([['readers'], []], [$change['from'], $change['to']]);
    }

    #[Test]
    public function only_roles_manage_writes_and_somebody_with_nothing_reads_nothing(): void
    {
        $assigner = $this->admin('assigner@example.test');
        $assigner->roles()->attach($this->role('assigners', ['roles.assign']));
        $role = $this->role('readers', ['cache.view']);

        // The form of an administrator offers the roles to whoever may assign them.
        $this->actingAs($assigner, 'cms')->getJson('/api/cms/auth/roles')->assertOk();
        $this->postJson('/api/cms/auth/roles', ['name' => 'X', 'permissions' => []])->assertForbidden();
        $this->putJson("/api/cms/auth/roles/{$role->id}", ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/cms/auth/roles/{$role->id}")->assertForbidden();

        $nobody = $this->admin('nobody@example.test');
        $this->actingAs($nobody, 'cms')->getJson('/api/cms/auth/roles')->assertForbidden();
        $this->getJson('/api/cms/auth/roles/permissions')->assertForbidden();
        $this->getJson("/api/cms/auth/roles/{$role->id}")->assertForbidden();
    }

    #[Test]
    public function a_super_administrator_is_held_to_none_of_it(): void
    {
        $super = $this->admin('super@example.test', super: true);

        $this->actingAs($super, 'cms')
            ->postJson('/api/cms/auth/roles', ['name' => 'Everything', 'slug' => 'all', 'permissions' => ['admins.manage', 'roles.manage', 'cache.reset']])
            ->assertCreated();

        foreach ($this->matrix() as $permissions) {
            foreach ($permissions as $name => $permission) {
                $this->assertTrue($permission['held'], $name);
            }
        }
    }

    /**
     * Somebody who may edit roles, and holds these permissions besides.
     *
     * @param  list<string>  $extra
     */
    private function editor(array $extra): CmsUser
    {
        $editor = $this->admin('editor@example.test');
        $editor->roles()->attach($this->role('editors', ['roles.manage', ...$extra]));

        return $editor->load('roles');
    }

    /**
     * The matrix, by module and then by permission.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function matrix(): array
    {
        $rows = [];

        foreach ($this->keyed($this->getJson('/api/cms/auth/roles/permissions')->assertOk()->json('data'), 'module') as $module => $row) {
            $rows[$module] = $this->keyed($row['permissions'] ?? [], 'name');
        }

        return $rows;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function keyed(mixed $list, string $key): array
    {
        $this->assertIsArray($list);
        $keyed = [];

        foreach ($list as $item) {
            $this->assertIsArray($item);
            $keyed[(string) $item[$key]] = $item;
        }

        return $keyed;
    }
}
