<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;

final class AdminEndpointsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_list_is_paginated_searchable_and_filtered(): void
    {
        $this->actingAs($this->admin('me@example.test', super: true), 'cms');

        $editors = $this->role('editors', ['pages.view']);
        $this->admin('anna@example.test')->update(['name' => 'Anna']);
        $switched = $this->admin('boris@example.test');
        $switched->update(['name' => 'Boris', 'is_active' => false]);
        CmsUser::query()->where('email', 'anna@example.test')->firstOrFail()
            ->roles()->attach($editors->getKey());

        $this->getJson('/api/cms/auth/admins?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/cms/auth/admins?q=anna')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'anna@example.test')
            ->assertJsonPath('data.0.roles.0.slug', 'editors');

        $this->getJson('/api/cms/auth/admins?role=editors')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/cms/auth/admins?active=no')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Boris');
    }

    #[Test]
    public function an_administrator_is_created_with_roles_and_never_hands_back_the_password(): void
    {
        $this->actingAs($this->admin('me@example.test', super: true), 'cms');
        $role = $this->role('editors', ['pages.view']);

        $response = $this->postJson('/api/cms/auth/admins', [
            'name' => 'Anna',
            'email' => 'anna@example.test',
            'password' => 'correct-horse-battery',
            'roles' => [$role->getKey()],
            'avatar' => 'media/ab/cd/anna.jpg',
        ])->assertCreated();

        $response
            ->assertJsonPath('data.email', 'anna@example.test')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.avatar', 'media/ab/cd/anna.jpg')
            ->assertJsonPath('data.roles.0.slug', 'editors');

        $this->assertArrayNotHasKey('password', (array) $response->json('data'));

        $created = CmsUser::query()->where('email', 'anna@example.test')->firstOrFail();
        $this->assertNotSame('correct-horse-battery', $created->password);
    }

    #[Test]
    public function a_short_password_is_refused_and_an_address_can_only_be_used_once(): void
    {
        $this->actingAs($this->admin('me@example.test', super: true), 'cms');
        $this->admin('anna@example.test');

        $this->postJson('/api/cms/auth/admins', [
            'name' => 'Anna',
            'email' => 'anna@example.test',
            'password' => 'correct-horse-battery',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson('/api/cms/auth/admins', [
            'name' => 'Boris',
            'email' => 'boris@example.test',
            'password' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    #[Test]
    public function an_edit_that_leaves_the_password_blank_leaves_the_password_alone(): void
    {
        $this->actingAs($this->admin('me@example.test', super: true), 'cms');
        $anna = $this->admin('anna@example.test');
        $was = $anna->password;

        $this->patchJson("/api/cms/auth/admins/{$anna->id}", [
            'name' => 'Anna Smith',
            'email' => 'anna@example.test',
        ])->assertOk()->assertJsonPath('data.name', 'Anna Smith');

        $this->assertSame($was, $anna->refresh()->password);
    }

    #[Test]
    public function nobody_can_lock_themselves_out_or_take_away_the_last_super(): void
    {
        $me = $this->admin('me@example.test', super: true);
        $this->actingAs($me, 'cms');

        // Switching yourself off is a locked door with the key inside.
        $this->patchJson("/api/cms/auth/admins/{$me->id}", [
            'name' => 'Me',
            'email' => 'me@example.test',
            'is_active' => false,
        ])->assertStatus(422);

        // And so is the last super administrator giving up being one.
        $this->patchJson("/api/cms/auth/admins/{$me->id}", [
            'name' => 'Me',
            'email' => 'me@example.test',
            'is_super' => false,
        ])->assertStatus(422);

        $this->deleteJson("/api/cms/auth/admins/{$me->id}")->assertStatus(422);

        $this->assertTrue($me->refresh()->is_super);
        $this->assertTrue($me->is_active);
    }

    #[Test]
    public function the_last_super_may_step_down_once_there_is_another(): void
    {
        $me = $this->admin('me@example.test', super: true);
        $this->admin('other@example.test', super: true);
        $this->actingAs($me, 'cms');

        $this->patchJson("/api/cms/auth/admins/{$me->id}", [
            'name' => 'Me',
            'email' => 'me@example.test',
            'is_super' => false,
        ])->assertOk()->assertJsonPath('data.is_super', false);
    }

    #[Test]
    public function deleting_takes_the_roles_with_it(): void
    {
        $this->actingAs($this->admin('me@example.test', super: true), 'cms');
        $role = $this->role('editors', ['pages.view']);
        $anna = $this->admin('anna@example.test');
        $anna->roles()->attach($role->getKey());

        $this->deleteJson("/api/cms/auth/admins/{$anna->id}")->assertOk();

        $this->assertSame(0, CmsUser::query()->where('email', 'anna@example.test')->count());
        $this->assertSame(0, $role->users()->count());
    }

    #[Test]
    public function reading_the_list_is_not_managing_it(): void
    {
        $reader = $this->admin('reader@example.test');
        $reader->roles()->attach($this->role('readers', ['admins.view'])->getKey());
        $this->actingAs($reader, 'cms');

        // A picker of people to assign work to needs the list and nothing else.
        $this->getJson('/api/cms/auth/admins')->assertOk();
        $this->getJson('/api/cms/auth/roles')->assertOk();

        $this->postJson('/api/cms/auth/admins', [
            'name' => 'Anna',
            'email' => 'anna@example.test',
            'password' => 'correct-horse-battery',
        ])->assertForbidden();
    }

    #[Test]
    public function the_roles_say_how_many_people_hold_them(): void
    {
        $this->actingAs($this->admin('me@example.test', super: true), 'cms');
        $role = $this->role('editors', ['pages.view']);
        $this->admin('anna@example.test')->roles()->attach($role->getKey());

        $this->getJson('/api/cms/auth/roles')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'editors')
            ->assertJsonPath('data.0.users', 1)
            ->assertJsonPath('data.0.permissions.0', 'pages.view');
    }
}
