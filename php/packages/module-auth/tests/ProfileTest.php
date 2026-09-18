<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;

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
        // The whole reason this endpoint can be open to everybody: roles, super and active
        // are not in it, so sending them changes nothing.
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/me', [
            'name' => 'Admin',
            'is_super' => true,
            'is_active' => false,
            'email' => 'someone.else@example.test',
        ])->assertOk();

        $this->assertFalse($admin->fresh()->is_super);
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame('admin@example.test', $admin->fresh()?->email);
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
