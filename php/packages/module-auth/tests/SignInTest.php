<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\LoginRecord;

final class SignInTest extends TestCase
{
    #[Test]
    public function an_administrator_signs_in(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['pages.view', 'pages.manage']));

        $this->postJson('/api/cms/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.test')
            ->assertJsonPath('data.isSuper', false)
            ->assertJsonPath('data.roles.0.slug', 'editor')
            ->assertJsonPath('data.permissions', ['pages.manage', 'pages.view']);

        $this->assertAuthenticatedAs($admin->fresh(), 'cms');
        $this->assertNotNull($admin->fresh()?->last_login_at);
    }

    #[Test]
    public function the_password_is_never_in_the_answer(): void
    {
        $this->admin();

        $response = $this->postJson('/api/cms/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
        ])->assertOk();

        $this->assertStringNotContainsString('password', $response->getContent() ?: '');
    }

    #[Test]
    public function a_wrong_password_fails_the_same_way_an_unknown_address_does(): void
    {
        $this->admin();

        $wrongPassword = $this->postJson('/api/cms/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'nope',
        ])->assertStatus(422);

        $unknownAddress = $this->postJson('/api/cms/auth/login', [
            'email' => 'nobody@example.test',
            'password' => 'nope',
        ])->assertStatus(422);

        // Telling these two apart would turn the form into a way to find out who has an account.
        $this->assertSame(
            $wrongPassword->json('errors.email'),
            $unknownAddress->json('errors.email'),
        );

        $this->assertGuest('cms');
    }

    #[Test]
    public function a_deactivated_account_cannot_sign_in_and_does_not_say_why(): void
    {
        $this->admin(active: false);

        $this->postJson('/api/cms/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('auth.failed'));

        $this->assertGuest('cms');
    }

    #[Test]
    public function every_attempt_is_written_down(): void
    {
        $this->admin();

        $this->postJson('/api/cms/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'nope',
        ])->assertStatus(422);

        $this->postJson('/api/cms/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
        ])->assertOk();

        $records = LoginRecord::query()->orderBy('id')->get();

        $this->assertCount(2, $records);
        $this->assertFalse($records[0]->successful);
        $this->assertTrue($records[1]->successful);
        $this->assertSame('admin@example.test', $records[0]->email);
    }

    #[Test]
    public function an_attempt_against_an_address_nobody_owns_is_written_down_too(): void
    {
        $this->postJson('/api/cms/auth/login', [
            'email' => 'ghost@example.test',
            'password' => 'nope',
        ])->assertStatus(422);

        $record = LoginRecord::query()->firstOrFail();

        $this->assertSame('ghost@example.test', $record->email);
        $this->assertNull($record->cms_user_id);
        $this->assertFalse($record->successful);
    }

    #[Test]
    public function signing_out_ends_the_session(): void
    {
        $this->admin();

        $this->postJson('/api/cms/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
        ])->assertOk();

        $this->postJson('/api/cms/auth/logout')->assertNoContent();

        $this->assertGuest('cms');
        $this->getJson('/api/cms/auth/me')->assertUnauthorized();
    }

    #[Test]
    public function me_answers_401_to_a_stranger(): void
    {
        $this->getJson('/api/cms/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function me_describes_whoever_is_signed_in(): void
    {
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'cms')
            ->getJson('/api/cms/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.test')
            ->assertJsonPath('data.isSuper', true);
    }

    #[Test]
    public function an_account_switched_off_mid_session_stops_working_at_once(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'cms')->getJson('/api/cms/auth/me')->assertOk();

        $admin->forceFill(['is_active' => false])->save();

        $this->actingAs($admin->fresh(), 'cms')
            ->getJson('/api/cms/auth/me')
            ->assertForbidden();
    }
}
