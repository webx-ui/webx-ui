<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

final class PermissionsTest extends TestCase
{
    #[Test]
    public function a_role_grants_what_it_lists(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['pages.view']));
        $admin->load('roles');

        $this->assertTrue($admin->hasPermission('pages.view'));
        $this->assertFalse($admin->hasPermission('pages.manage'));
        $this->assertTrue($admin->hasRole('editor'));
        $this->assertFalse($admin->hasRole('owner'));
    }

    #[Test]
    public function a_super_administrator_passes_every_check(): void
    {
        $admin = $this->admin(super: true);

        $this->assertTrue($admin->hasPermission('anything.at.all'));
        // Without this the first install could never grant the first permission.
        $this->assertSame([], $admin->permissions());
    }

    #[Test]
    public function permissions_arrive_flattened_deduplicated_and_sorted(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['pages.view', 'media.view'])->getKey());
        $admin->roles()->attach($this->role('reviewer', ['pages.view', 'pages.publish'])->getKey());
        $admin->load('roles');

        $this->assertSame(
            ['media.view', 'pages.publish', 'pages.view'],
            $admin->permissions(),
        );
    }

    #[Test]
    public function the_middleware_turns_a_permission_into_a_403(): void
    {
        Route::middleware(['web', 'cms.auth', 'cms.can:pages.manage'])
            ->get('/probe', fn (): string => 'in');

        $allowed = $this->admin('allowed@example.test');
        $allowed->roles()->attach($this->role('editor', ['pages.manage']));

        $denied = $this->admin('denied@example.test');

        $this->actingAs($allowed, 'cms')->get('/probe')->assertOk();

        $this->actingAs($denied, 'cms')->getJson('/probe')
            ->assertForbidden()
            ->assertJsonPath('required', ['pages.manage']);
    }

    #[Test]
    public function the_middleware_accepts_any_of_several_permissions(): void
    {
        Route::middleware(['web', 'cms.auth', 'cms.can:pages.manage,pages.publish'])
            ->get('/probe-any', fn (): string => 'in');

        $admin = $this->admin();
        $admin->roles()->attach($this->role('reviewer', ['pages.publish']));

        $this->actingAs($admin, 'cms')->get('/probe-any')->assertOk();
    }

    #[Test]
    public function the_middleware_answers_401_before_it_answers_403(): void
    {
        Route::middleware(['web', 'cms.can:pages.manage'])->get('/probe-guest', fn (): string => 'in');

        $this->getJson('/probe-guest')->assertUnauthorized();
    }
}
