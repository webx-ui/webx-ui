<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Auth\Access\AccessRules;
use WebxUi\Auth\Access\Refusal;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * The rules no permission lifts (WEBX_UI_PERMISSIONS.md §6.2), one test a rule, through the
 * panel's API and through MCP where the agent has a door to it. Everybody here may manage
 * administrators and assign roles; what they may not do is the point.
 */
final class AccessRulesTest extends TestCase
{
    #[Test]
    public function rule_1_your_own_roles_are_somebody_elses_to_change(): void
    {
        $manager = $this->manager();
        $editor = $this->role('editor', ['pages.view']);

        $this->actingAs($manager, 'cms')
            ->patchJson("/api/cms/auth/admins/{$manager->id}", ['roles' => [...$this->roleIds($manager), $editor->id]])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::OWN_ROLES);

        $this->assertSame(['ok' => false, 'rule' => Refusal::OWN_ROLES], $this->grant($manager, $manager, 'editor'));
        $this->assertSame(['ok' => false, 'rule' => Refusal::OWN_ROLES], $this->revoke($manager, $manager, 'people'));

        $this->assertFalse($manager->refresh()->hasRole('editor'));
        $this->assertTrue($manager->hasRole('people'));
    }

    #[Test]
    public function rule_2_nobody_hands_out_a_permission_they_do_not_hold(): void
    {
        $manager = $this->manager();
        $other = $this->admin('other@example.test');
        $owner = $this->role('owner', ['pages.view', 'pages.manage']);

        $this->actingAs($manager, 'cms')
            ->patchJson("/api/cms/auth/admins/{$other->id}", ['roles' => [$owner->id]])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::BEYOND_YOURS)
            ->assertJsonPath('missing', ['pages.manage']);

        // Nor by making a new account with it, and signing in as that.
        $this->postJson('/api/cms/auth/admins', [
            'name' => 'Puppet',
            'email' => 'puppet@example.test',
            'password' => 'correct-horse-battery',
            'roles' => [$owner->id],
        ])->assertForbidden()->assertJsonPath('rule', Refusal::BEYOND_YOURS);

        $this->assertNull(CmsUser::query()->where('email', 'puppet@example.test')->first());
        $this->assertSame(['ok' => false, 'rule' => Refusal::BEYOND_YOURS], $this->grant($manager, $other, 'owner'));
        $this->assertFalse($other->refresh()->hasRole('owner'));

        // What they hold they may hand out.
        $this->role('reader', ['pages.view']);
        $this->assertTrue($this->grant($manager, $other, 'reader')['ok']);
        $this->assertTrue($other->refresh()->hasRole('reader'));
    }

    #[Test]
    public function rule_2_nobody_takes_over_an_account_that_holds_more_than_they_do(): void
    {
        $manager = $this->manager();
        $stronger = $this->admin('stronger@example.test');
        $stronger->roles()->attach($this->role('owner', ['pages.manage']));

        $this->actingAs($manager, 'cms')
            ->patchJson("/api/cms/auth/admins/{$stronger->id}", ['email' => 'mine@example.test', 'password' => 'a-new-password-1'])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::BEYOND_YOURS);

        $this->deleteJson("/api/cms/auth/admins/{$stronger->id}")
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::BEYOND_YOURS);

        $this->assertSame('stronger@example.test', $stronger->refresh()->email);

        // Taking a role away hands out nothing: allowed.
        $this->assertTrue($this->revoke($manager, $stronger, 'owner')['ok']);
    }

    #[Test]
    public function rule_2_a_role_gains_only_what_its_editor_holds(): void
    {
        $manager = $this->manager(['roles.manage']);
        $role = $this->role('writers', ['pages.view']);
        $rules = $this->app->make(AccessRules::class);

        $this->assertSame(Refusal::BEYOND_YOURS, $rules->saveRole($manager, $role, ['pages.view', 'pages.manage'])?->rule);
        $this->assertSame(Refusal::BEYOND_YOURS, $rules->saveRole($manager, null, ['pages.manage'])?->rule);
        $this->assertNull($rules->saveRole($manager, $role, ['pages.view', 'admins.view']));

        // Without the permission to manage roles at all, nothing.
        $this->assertSame(Refusal::FORBIDDEN, $rules->saveRole($this->manager([], 'plain@example.test'), $role, ['pages.view'])?->rule);
    }

    #[Test]
    public function rule_3_only_a_super_administrator_makes_or_unmakes_one(): void
    {
        $manager = $this->manager();
        $other = $this->admin('other@example.test');

        $this->actingAs($manager, 'cms')
            ->postJson('/api/cms/auth/admins', [
                'name' => 'New',
                'email' => 'new@example.test',
                'password' => 'correct-horse-battery',
                'is_super' => true,
            ])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::ONLY_SUPER);

        $this->patchJson("/api/cms/auth/admins/{$other->id}", ['is_super' => true])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::ONLY_SUPER);

        $this->patchJson("/api/cms/auth/admins/{$manager->id}", ['is_super' => true])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::ONLY_SUPER);

        $this->assertFalse($other->refresh()->is_super);
        $this->assertFalse($manager->refresh()->is_super);
    }

    #[Test]
    public function rule_4_only_a_super_administrator_touches_one(): void
    {
        $manager = $this->manager();
        $super = $this->admin('super@example.test', super: true);
        $this->role('reader', ['pages.view']);

        $this->actingAs($manager, 'cms')
            ->patchJson("/api/cms/auth/admins/{$super->id}", ['email' => 'taken@example.test'])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::ONLY_SUPER);

        $this->patchJson("/api/cms/auth/admins/{$super->id}", ['is_active' => false])->assertForbidden();
        $this->deleteJson("/api/cms/auth/admins/{$super->id}")->assertForbidden();

        $this->assertSame(['ok' => false, 'rule' => Refusal::ONLY_SUPER], $this->grant($manager, $super, 'reader'));

        $this->assertSame('super@example.test', $super->refresh()->email);
        $this->assertTrue($super->is_active);
    }

    #[Test]
    public function rule_5_a_role_you_hold_is_somebody_elses_to_edit(): void
    {
        $manager = $this->manager(['roles.manage']);
        $rules = $this->app->make(AccessRules::class);
        $people = Role::query()->where('slug', 'people')->sole();

        $this->assertSame(Refusal::ROLE_YOU_HOLD, $rules->saveRole($manager, $people, $people->permissions)?->rule);
        $this->assertSame(Refusal::ROLE_YOU_HOLD, $rules->deleteRole($manager, $people)?->rule);

        // A super administrator is not limited by roles, the ones they hold included.
        $super = $this->admin('super@example.test', super: true);
        $super->roles()->attach($people);
        $this->assertNull($rules->saveRole($super, $people, ['pages.manage']));
    }

    #[Test]
    public function rule_6_the_last_active_super_administrator_stays_one(): void
    {
        $me = $this->admin('me@example.test', super: true);
        // Another one, switched off: not somebody who can sign in and put things back.
        $this->admin('asleep@example.test', super: true, active: false);

        $this->actingAs($me, 'cms')
            ->patchJson("/api/cms/auth/admins/{$me->id}", ['is_super' => false])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::LAST_SUPER);

        $this->assertTrue($me->refresh()->is_super);

        // With a second one awake, either may step down.
        $awake = $this->admin('awake@example.test', super: true);

        $this->patchJson("/api/cms/auth/admins/{$awake->id}", ['is_active' => false])->assertOk();
        $this->patchJson("/api/cms/auth/admins/{$awake->id}", ['is_active' => true])->assertOk();
        $this->patchJson("/api/cms/auth/admins/{$me->id}", ['is_super' => false])->assertOk();
    }

    #[Test]
    public function rule_8_every_refusal_is_written_down(): void
    {
        $manager = $this->manager();
        $super = $this->admin('super@example.test', super: true);

        $this->actingAs($manager, 'cms')
            ->deleteJson("/api/cms/auth/admins/{$super->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Only a super administrator can do that.');

        $row = HistoryEntry::query()->where('event', HistoryEntry::REFUSED)->sole();

        $this->assertSame(CmsUser::TYPE, $row->subject_type);
        $this->assertSame($super->id, $row->subject_id);
        $this->assertSame($manager->id, $row->admin_id);
        $this->assertSame([['field' => 'refused', 'from' => 'delete', 'to' => Refusal::ONLY_SUPER]], array_map(
            static fn (array $change): array => array_intersect_key($change, array_flip(['field', 'from', 'to'])),
            $row->changes ?? [],
        ));

        // Through MCP too.
        $this->revoke($manager, $manager, 'people');
        $this->assertSame(2, HistoryEntry::query()->where('event', HistoryEntry::REFUSED)->count());
    }

    #[Test]
    public function assigning_roles_is_a_permission_of_its_own(): void
    {
        $manager = $this->manager([], withAssign: false);
        $other = $this->admin('other@example.test');
        $reader = $this->role('reader', ['pages.view']);

        $this->actingAs($manager, 'cms')
            ->patchJson("/api/cms/auth/admins/{$other->id}", ['roles' => [$reader->id]])
            ->assertForbidden()
            ->assertJsonPath('rule', Refusal::FORBIDDEN)
            ->assertJsonPath('missing', ['roles.assign']);

        // The form sends the roles back as they were with every save: that is not assigning.
        $this->patchJson("/api/cms/auth/admins/{$other->id}", ['name' => 'Other', 'roles' => []])->assertOk();

        // And the agent's tool is behind it, so it is not even listed.
        $tool = $this->app->make(ToolRegistry::class)->tool('admins_grant_role');
        $this->assertSame(['roles.assign'], $tool->permissions());
    }

    #[Test]
    public function an_edit_changes_what_it_sends_and_nothing_else(): void
    {
        $me = $this->admin('me@example.test', super: true);
        $other = $this->admin('other@example.test', super: true, active: false);

        // No `is_super`, no `is_active`: neither is taken away nor switched on.
        $this->actingAs($me, 'cms')
            ->patchJson("/api/cms/auth/admins/{$other->id}", ['name' => 'Renamed'])
            ->assertOk();

        $other->refresh();
        $this->assertSame('Renamed', $other->name);
        $this->assertTrue($other->is_super);
        $this->assertFalse($other->is_active);
    }

    #[Test]
    public function who_holds_which_role_goes_into_the_journal(): void
    {
        $super = $this->admin('super@example.test', super: true);
        $other = $this->admin('other@example.test');
        $reader = $this->role('reader', ['pages.view']);
        $writer = $this->role('writer', ['pages.manage']);

        $this->actingAs($super, 'cms')
            ->patchJson("/api/cms/auth/admins/{$other->id}", ['roles' => [$reader->id, $writer->id]])
            ->assertOk();

        $this->revoke($super, $other, 'writer');

        $rows = HistoryEntry::query()
            ->where('subject_type', CmsUser::TYPE)
            ->where('subject_id', $other->id)
            ->where('event', HistoryEntry::UPDATED)
            ->orderBy('id')
            ->get();

        $roles = $rows->map(static fn (HistoryEntry $row): array => collect($row->changes ?? [])->firstWhere('field', 'roles') ?? [])
            ->filter()
            ->map(static fn (array $change): array => [$change['from'], $change['to']])
            ->values()
            ->all();

        $this->assertSame([[[], ['reader', 'writer']], [['reader', 'writer'], ['reader']]], $roles);

        // And a role's own permissions, when they change.
        $reader->update(['permissions' => ['pages.view', 'media.view']]);

        $this->assertTrue(HistoryEntry::query()
            ->where('subject_type', Role::TYPE)
            ->where('subject_id', $reader->id)
            ->where('event', HistoryEntry::UPDATED)
            ->exists());
    }

    /**
     * Somebody who may manage administrators and assign roles, and also read pages: the role
     * `people`.
     *
     * @param  list<string>  $extra
     */
    private function manager(array $extra = [], string $email = 'manager@example.test', bool $withAssign = true): CmsUser
    {
        $manager = $this->admin($email);
        $permissions = ['admins.view', 'admins.manage', 'pages.view', ...$extra];

        if ($withAssign) {
            $permissions[] = 'roles.assign';
        }

        $slug = $email === 'manager@example.test' ? 'people' : 'people-'.md5($email);
        $manager->roles()->attach($this->role($slug, $permissions));

        return $manager->load('roles');
    }

    /**
     * @return list<int>
     */
    private function roleIds(CmsUser $admin): array
    {
        return $admin->roles()->pluck('cms_roles.id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function grant(CmsUser $as, CmsUser $target, string $role): array
    {
        return $this->callTool('admins_grant_role', $as, $target, $role);
    }

    /**
     * @return array<string, mixed>
     */
    private function revoke(CmsUser $as, CmsUser $target, string $role): array
    {
        return $this->callTool('admins_revoke_role', $as, $target, $role);
    }

    /**
     * The tool's handler as the server runs it — the refusal comes back as `ok: false` with the
     * rule, the shape an agent reads; only those two are compared when it is a refusal.
     *
     * @return array<string, mixed>
     */
    private function callTool(string $tool, CmsUser $as, CmsUser $target, string $role): array
    {
        $handler = $this->app->make(ToolRegistry::class)->tool($tool)->tool->handler;
        /** @var array<string, mixed> $result */
        $result = $handler(['email' => $target->email, 'role' => $role], $as->refresh());

        return $result['ok'] === false && isset($result['rule'])
            ? ['ok' => false, 'rule' => $result['rule']]
            : $result;
    }
}
