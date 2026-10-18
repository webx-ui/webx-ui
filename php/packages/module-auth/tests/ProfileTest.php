<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Auth\Models\CmsUser;

/**
 * Editing yourself.
 *
 * The rules worth holding on to are the two negatives: what this cannot change, and what it
 * will not change without the current password.
 */
final class ProfileTest extends TestCase
{
    #[Test]
    public function anybody_signed_in_may_edit_their_own_name_and_photograph(): void
    {
        // Not behind `admins.manage`: that permission is about other people, and an editor who
        // holds none of it still has a name to spell.
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/me', ['name' => 'Anna Petrova', 'avatar' => 'people/anna.jpg'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Anna Petrova')
            ->assertJsonPath('data.avatar', 'people/anna.jpg');

        $this->assertSame('Anna Petrova', $admin->fresh()?->name);
        $this->assertSame('people/anna.jpg', $admin->fresh()->avatar);
    }

    #[Test]
    public function what_a_person_is_allowed_to_do_is_not_theirs_to_change(): void
    {
        // The whole reason this endpoint can be open to everybody: roles, super, active and the
        // address are not in it — and sending them is refused by name, not quietly dropped
        // (WEBX_UI_PERMISSIONS.md §8).
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/me', [
            'name' => 'Renamed',
            'is_super' => true,
            'is_active' => false,
            'roles' => [1],
            'permissions' => ['admins.manage'],
            'email' => 'someone.else@example.test',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_super', 'is_active', 'roles', 'permissions', 'email'])
            ->assertJsonMissingValidationErrors('name');

        $fresh = $admin->fresh();
        $this->assertSame('Admin', $fresh?->name);
        $this->assertFalse($fresh->is_super);
        $this->assertTrue($fresh->is_active);
        $this->assertSame('admin@example.test', $fresh->email);
        $this->assertSame([], $fresh->roles()->pluck('id')->all());
    }

    #[Test]
    public function a_method_override_is_not_a_field(): void
    {
        // A host that lets through only GET and POST sends the real method in `_method`.
        $this->actingAs($this->admin(), 'cms');

        $this->postJson('api/cms/auth/me', ['_method' => 'PUT', 'name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    #[Test]
    public function every_change_of_yourself_is_in_the_history_and_the_password_only_as_a_fact(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/me', [
            'name' => 'Renamed',
            'password' => 'a-much-longer-secret',
            'current_password' => 'correct-horse-battery',
        ])->assertOk();

        $rows = HistoryEntry::query()
            ->where('subject_type', CmsUser::TYPE)
            ->where('subject_id', $admin->id)
            ->where('event', HistoryEntry::UPDATED)
            ->get();
        $fields = $rows->flatMap(static fn (HistoryEntry $row): array => array_column($row->changes ?? [], 'field'))->all();

        $this->assertContains('name', $fields);
        $this->assertContains('password', $fields);
        $this->assertSame($admin->id, $rows->first()?->admin_id);
        $this->assertStringNotContainsString('a-much-longer-secret', (string) json_encode($rows->pluck('changes')));
    }

    #[Test]
    public function the_password_changes_only_when_the_current_one_is_given(): void
    {
        // A session left open on somebody else's machine should not be enough to lock its
        // owner out of their own panel.
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/me', [
            'name' => 'Admin',
            'password' => 'a-much-longer-secret',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->putJson('api/cms/auth/me', [
            'name' => 'Admin',
            'password' => 'a-much-longer-secret',
            'current_password' => 'wrong-horse-battery',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('correct-horse-battery', (string) $admin->fresh()?->password));

        $this->putJson('api/cms/auth/me', [
            'name' => 'Admin',
            'password' => 'a-much-longer-secret',
            'current_password' => 'correct-horse-battery',
        ])->assertOk();

        $this->assertTrue(Hash::check('a-much-longer-secret', (string) $admin->fresh()?->password));
    }

    #[Test]
    public function a_blank_password_is_an_edit_of_everything_else(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/me', ['name' => 'Renamed', 'password' => ''])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertTrue(Hash::check('correct-horse-battery', (string) $admin->fresh()?->password));
    }

    #[Test]
    public function a_visitor_has_no_profile_to_edit(): void
    {
        $this->putJson('api/cms/auth/me', ['name' => 'Nobody'])->assertUnauthorized();
    }
}
