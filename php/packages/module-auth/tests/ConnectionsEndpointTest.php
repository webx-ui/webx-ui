<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WebxUi\Mcp\Grants\Grant;

/**
 * The connections tab: everybody sees their own, `admins.manage` sees everybody's, and
 * disconnecting is a decision the person who made the connection may take.
 */
final class ConnectionsEndpointTest extends TestCase
{
    #[Test]
    public function everybody_sees_their_own_and_only_their_own(): void
    {
        $anna = $this->admin('anna@example.test');
        $boris = $this->admin('boris@example.test');

        $this->grant($anna->getKey(), 'Claude');
        $this->grant($boris->getKey(), 'Cursor');

        $this->actingAs($anna, 'cms');

        $response = $this->getJson('/api/cms/auth/connections')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.client', 'Claude');
        // No switch to everybody's, because asking for them would be refused.
        $response->assertJsonPath('meta.can_see_everybody', false);
    }

    #[Test]
    public function everybody_elses_is_behind_the_manage_permission(): void
    {
        $anna = $this->admin('anna@example.test');
        $this->grant($anna->getKey(), 'Claude');

        $this->actingAs($anna, 'cms');
        $this->getJson('/api/cms/auth/connections?all=1')->assertForbidden();

        $manager = $this->admin('manager@example.test');
        $manager->roles()->attach($this->role('managers', ['admins.manage']));
        $this->grant($manager->getKey(), 'Cursor');

        $this->actingAs($manager, 'cms');

        $response = $this->getJson('/api/cms/auth/connections?all=1')->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.scope', 'all');
        $response->assertJsonPath('meta.can_see_everybody', true);
    }

    #[Test]
    public function a_live_connection_is_listed_before_one_that_was_ended(): void
    {
        $anna = $this->admin('anna@example.test');

        $ended = $this->grant($anna->getKey(), 'Codex');
        $ended->forceFill(['revoked_at' => Carbon::now(), 'last_used_at' => Carbon::now()])->save();

        $this->grant($anna->getKey(), 'Claude');

        $this->actingAs($anna, 'cms');

        $response = $this->getJson('/api/cms/auth/connections')->assertOk();

        $response->assertJsonPath('data.0.client', 'Claude');
        $response->assertJsonPath('data.0.revoked_at', null);
        $response->assertJsonPath('data.1.client', 'Codex');
    }

    #[Test]
    public function you_may_end_your_own_connection_and_not_somebody_elses(): void
    {
        $anna = $this->admin('anna@example.test');
        $boris = $this->admin('boris@example.test');

        $mine = $this->grant($anna->getKey(), 'Claude');
        $theirs = $this->grant($boris->getKey(), 'Cursor');

        $this->actingAs($anna, 'cms');

        $this->deleteJson("/api/cms/auth/connections/{$theirs->getKey()}")->assertForbidden();
        $this->assertNull($theirs->refresh()->revoked_at);

        $this->deleteJson("/api/cms/auth/connections/{$mine->getKey()}")->assertOk();
        $this->assertNotNull($mine->refresh()->revoked_at);
    }

    #[Test]
    public function somebody_who_manages_administrators_may_end_anybodys(): void
    {
        $anna = $this->admin('anna@example.test');
        $theirs = $this->grant($anna->getKey(), 'Claude');

        $manager = $this->admin('manager@example.test');
        $manager->roles()->attach($this->role('managers', ['admins.manage']));

        $this->actingAs($manager, 'cms');

        $this->deleteJson("/api/cms/auth/connections/{$theirs->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.client', 'Claude');

        $this->assertNotNull($theirs->refresh()->revoked_at);
    }

    #[Test]
    public function a_guest_is_told_to_sign_in_rather_than_shown_an_empty_list(): void
    {
        $this->getJson('/api/cms/auth/connections')->assertUnauthorized();
    }

    /**
     * Ending a connection reaches into Passport's tokens, so the tables have to be there —
     * on a site they arrive with `vendor:publish --tag=passport-migrations`.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Passport::class))->getFileName(), 2).'/database/migrations');

        parent::defineDatabaseMigrations();
    }

    private function grant(int $user, string $client): Grant
    {
        return Grant::query()->create([
            'cms_user_id' => $user,
            'oauth_client_id' => sprintf('%s-0a7b-4c3d-8e5f-1a2b3c4d5e6f', substr(md5($client.$user), 0, 8)),
            'client_name' => $client,
            'redirect_host' => strtolower($client).'.test',
            'read_only' => $client === 'Cursor',
            'consent_version' => '2026-09-21',
            'created_at' => Carbon::now(),
        ]);
    }
}
