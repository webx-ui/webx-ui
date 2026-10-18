<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Calls\Call;
use WebxUi\Mcp\Keys\Key;
use WebxUi\Mcp\Keys\Keys;

/**
 * Administrators → Keys: issuing a key for a program, what it does once issued, and switching
 * it off.
 */
final class KeysEndpointTest extends PassportTestCase
{
    #[Test]
    public function the_keys_are_behind_the_manage_permission(): void
    {
        $this->getJson('/api/cms/auth/keys')->assertUnauthorized();

        $editor = $this->admin('editor@example.test');
        $this->actingAs($editor, 'cms');

        $this->getJson('/api/cms/auth/keys')->assertForbidden();
        $this->postJson('/api/cms/auth/keys', $this->input($editor))->assertForbidden();
        $this->assertSame(0, Key::query()->count());
    }

    #[Test]
    public function a_key_acts_as_the_administrator_it_was_issued_for(): void
    {
        $manager = $this->manager();
        $robot = $this->admin('robot@example.test');
        $robot->roles()->attach($this->role('readers', ['admins.view']));

        $this->actingAs($manager, 'cms');

        $response = $this->postJson('/api/cms/auth/keys', $this->input($robot, ['admins:read']))->assertCreated();

        $token = (string) $response->json('token');
        $this->assertNotSame('', $token);
        $response->assertJsonPath('data.user.id', $robot->getKey());
        $response->assertJsonPath('data.created_by.id', $manager->getKey());
        $response->assertJsonPath('data.scopes', ['admins:read']);
        $response->assertJsonPath('data.everything', false);
        $response->assertJsonPath('data.state', 'live');

        $this->mcp('admins_list_admins', $token)->assertJsonPath('result.isError', false);

        // The call log names the administrator the key acts as, not the one who issued it.
        $this->assertSame((int) $robot->getKey(), (int) Call::query()->sole()->cms_user_id);
        $this->assertNotNull(Key::query()->sole()->last_used_at);
    }

    #[Test]
    public function a_key_sees_only_the_tools_of_its_scopes(): void
    {
        $this->actingAs($this->admin('issuer@example.test', super: true), 'cms');
        $owner = $this->admin('owner@example.test', super: true);

        $token = (string) $this->postJson('/api/cms/auth/keys', $this->input($owner, ['admins:read']))->json('token');

        $names = array_column((array) $this->rpc($token, 'tools/list')->json('result.tools'), 'name');

        $this->assertContains('admins_list_admins', $names);
        // Behind `admins:write`: the owner may, the key may not, so it is not offered either.
        $this->assertNotContains('admins_grant_role', $names);

        // Out of scope is out of the list, and a tool not in the list is not there to call.
        $this->mcp('admins_grant_role', $token, ['email' => 'owner@example.test', 'role' => 'editors'])
            ->assertBadRequest()
            ->assertJsonPath('error.message', 'Tool [admins_grant_role] not found.');
    }

    #[Test]
    public function only_a_super_administrator_issues_a_key_for_one(): void
    {
        $this->actingAs($this->manager(), 'cms');
        $owner = $this->admin('owner@example.test', super: true);

        // The key would act as the owner, and the manager would hold it.
        $this->postJson('/api/cms/auth/keys', $this->input($owner))
            ->assertForbidden()
            ->assertJsonPath('message', 'Only a super administrator can do that.');

        $this->assertSame(0, Key::query()->count());
    }

    #[Test]
    public function the_lifetime_is_chosen_up_to_ten_years(): void
    {
        $this->actingAs($this->admin('issuer@example.test', super: true), 'cms');
        $owner = $this->admin('owner@example.test', super: true);

        $response = $this->postJson('/api/cms/auth/keys', $this->input($owner, [], 3650))->assertCreated();

        $expires = Carbon::parse((string) $response->json('data.expires_at'));
        $this->assertTrue(now()->addDays(3649)->lt($expires));
        $response->assertJsonPath('data.everything', true);

        $this->postJson('/api/cms/auth/keys', $this->input($owner, [], 3651))->assertJsonValidationErrors('days');
        $this->postJson('/api/cms/auth/keys', $this->input($owner, [], 0))->assertJsonValidationErrors('days');

        // Signed into the token: a key that the door turned away would read the same up to here.
        $this->mcp('admins_list_admins', (string) $response->json('token'))->assertJsonPath('result.isError', false);
    }

    #[Test]
    public function what_cannot_be_issued_is_refused_and_nothing_is_written(): void
    {
        $this->actingAs($this->admin('issuer@example.test', super: true), 'cms');
        $retired = $this->admin('retired@example.test', active: false);
        $owner = $this->admin('owner@example.test', super: true);

        $this->postJson('/api/cms/auth/keys', $this->input($retired))->assertJsonValidationErrors('admin');
        $this->postJson('/api/cms/auth/keys', $this->input($owner, ['pages:write']))->assertJsonValidationErrors('scopes.0');
        $this->postJson('/api/cms/auth/keys', [...$this->input($owner), 'name' => ''])->assertJsonValidationErrors('name');
        $this->postJson('/api/cms/auth/keys', [...$this->input($owner), 'admin' => 999])->assertJsonValidationErrors('admin');

        $this->assertSame(0, Key::query()->count());
    }

    #[Test]
    public function the_list_never_shows_a_secret_and_includes_keys_from_the_console(): void
    {
        $owner = $this->admin('owner@example.test', super: true);
        $this->assertSame(0, Artisan::call('webx:mcp:token', ['--name' => 'deploy']));
        $printed = trim(Artisan::output());

        $this->actingAs($this->manager(), 'cms');

        $response = $this->getJson('/api/cms/auth/keys')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'deploy');
        $response->assertJsonPath('data.0.user.id', $owner->getKey());
        // Issued in a terminal, by nobody the panel knows.
        $response->assertJsonPath('data.0.created_by', null);
        $this->assertContains('admins:read', (array) $response->json('meta.scopes'));
        $this->assertContains('mcp:use', (array) $response->json('meta.scopes'));
        $this->assertStringNotContainsString($printed, (string) $response->getContent());
    }

    #[Test]
    public function a_revoked_key_stops_at_once_and_stays_in_the_list(): void
    {
        $this->actingAs($this->admin('issuer@example.test', super: true), 'cms');
        $owner = $this->admin('owner@example.test', super: true);

        $issued = $this->postJson('/api/cms/auth/keys', $this->input($owner))->assertCreated();
        $token = (string) $issued->json('token');
        $id = (int) $issued->json('data.id');

        $this->mcp('admins_list_admins', $token)->assertJsonPath('result.isError', false);

        $second = $this->manager('second@example.test');
        $this->actingAs($second, 'cms');
        $this->deleteJson("/api/cms/auth/keys/{$id}")
            ->assertOk()
            ->assertJsonPath('data.state', 'revoked');

        $this->mcp('admins_list_admins', $token)->assertUnauthorized();

        // The call above forgot who was signed in, as a request of its own would.
        $this->actingAs($second, 'cms');
        $this->getJson('/api/cms/auth/keys')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.state', 'revoked');
    }

    #[Test]
    public function a_personal_token_from_before_keys_is_a_program_too(): void
    {
        $owner = $this->admin('owner@example.test', super: true);
        $this->assertSame(0, Artisan::call('webx:mcp:token', ['--name' => 'old']));
        // As a token issued before keys had rows of their own: Passport's row and nothing else.
        Key::query()->delete();

        $token = Token::query()->sole();
        $owner->withAccessToken(new AccessToken([
            'oauth_access_token_id' => $token->getKey(),
            'oauth_client_id' => $token->getAttribute('client_id'),
            'oauth_user_id' => (string) $owner->getKey(),
            'oauth_scopes' => ['mcp:use'],
        ]));

        $this->assertTrue($this->app->make(Keys::class)->isKey($owner));
    }

    #[Test]
    public function a_site_updated_before_it_migrated_still_lets_agents_in(): void
    {
        $this->admin('owner@example.test', super: true);
        $this->assertSame(0, Artisan::call('webx:mcp:token', ['--name' => 'old']));
        $token = trim(Artisan::output());

        // The code of this release on the schema of the last one.
        Schema::drop('mcp_keys');

        $this->mcp('admins_list_admins', $token)->assertJsonPath('result.isError', false);
    }

    private function manager(string $email = 'manager@example.test'): CmsUser
    {
        $manager = $this->admin($email);
        $manager->roles()->attach($this->role('managers-'.md5($email), ['admins.manage']));

        return $manager;
    }

    /**
     * @param  list<string>  $scopes
     * @return array<string, mixed>
     */
    private function input(CmsUser $admin, array $scopes = [], int $days = 365): array
    {
        return ['name' => 'queue watcher', 'admin' => $admin->getKey(), 'scopes' => $scopes, 'days' => $days];
    }
}
