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
use WebxUi\Auth\Models\Role;
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
    public function your_own_keys_need_no_permission_and_somebody_elses_need_manage(): void
    {
        $this->getJson('/api/cms/auth/keys')->assertUnauthorized();

        $editor = $this->admin('editor@example.test');
        $editor->roles()->attach($this->role('readers', ['admins.view']));
        $other = $this->admin('other@example.test');
        $this->actingAs($editor, 'cms');

        // Your own: no more than you already are.
        $mine = $this->postJson('/api/cms/auth/keys', $this->input($editor, ['admins:read']))->assertCreated();

        // Somebody else's: speaking for them.
        $this->postJson('/api/cms/auth/keys', $this->input($other))
            ->assertForbidden()
            ->assertJsonPath('rule', 'forbidden')
            ->assertJsonPath('missing', ['admins.manage']);
        $this->getJson('/api/cms/auth/keys/scopes?admin='.$other->getKey())->assertForbidden();

        $this->actingAs($this->admin('issuer@example.test', super: true), 'cms');
        $theirs = (int) $this->postJson('/api/cms/auth/keys', $this->input($other))->assertCreated()->json('data.id');

        $this->actingAs($editor, 'cms');
        $this->getJson('/api/cms/auth/keys')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (int) $mine->json('data.id'))
            ->assertJsonPath('meta.can_see_everybody', false);

        $this->deleteJson("/api/cms/auth/keys/{$theirs}")->assertForbidden();
        $this->deleteJson('/api/cms/auth/keys/'.(int) $mine->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.state', 'revoked');
    }

    #[Test]
    public function a_key_carries_only_the_scopes_its_owners_permissions_reach(): void
    {
        $robot = $this->admin('robot@example.test');
        $robot->roles()->attach($this->role('readers', ['admins.view']));
        $this->actingAs($this->admin('issuer@example.test', super: true), 'cms');

        $usable = (array) $this->getJson('/api/cms/auth/keys/scopes?admin='.$robot->getKey())->assertOk()->json('data');

        $this->assertContains('mcp:use', $usable);
        $this->assertContains('admins:read', $usable);
        // Writing roles and reading the sign-in trail are behind permissions the robot lacks.
        $this->assertNotContains('admins:write', $usable);
        $this->assertNotContains('admins:audit', $usable);

        // Even a super administrator cannot hand the robot a scope its owner could never use.
        $this->postJson('/api/cms/auth/keys', $this->input($robot, ['admins:read', 'admins:write']))
            ->assertJsonValidationErrors('scopes');
        $this->assertSame(0, Key::query()->count());

        // The console is the same door.
        $this->assertSame(1, Artisan::call('webx:mcp:token', ['--name' => 'cron', '--admin' => 'robot@example.test', '--scopes' => 'admins:audit']));
        $this->assertSame(0, Key::query()->count());

        $this->postJson('/api/cms/auth/keys', $this->input($robot, ['admins:read']))->assertCreated();
    }

    #[Test]
    public function a_role_taken_from_the_owner_narrows_their_key_at_once(): void
    {
        $robot = $this->admin('robot@example.test');
        $readers = $this->role('readers', ['admins.view']);
        $robot->roles()->attach($readers);
        $this->actingAs($this->admin('issuer@example.test', super: true), 'cms');

        // Both the module scope and `mcp:use`: each is the owner's permissions of the moment.
        foreach ([['admins:read'], []] as $scopes) {
            $robot->roles()->sync([$readers->getKey()]);
            $this->actingAs($this->admin('issuer-'.count($scopes).'@example.test', super: true), 'cms');
            $token = (string) $this->postJson('/api/cms/auth/keys', $this->input($robot, $scopes))->assertCreated()->json('token');

            $this->mcp('admins_list_admins', $token)->assertJsonPath('result.isError', false);
            $this->assertContains('admins_list_admins', array_column((array) $this->rpc($token, 'tools/list')->json('result.tools'), 'name'));

            $robot->roles()->detach();

            // Out of the list is out of reach: the tool is not there to call any more.
            $this->mcp('admins_list_admins', $token)
                ->assertBadRequest()
                ->assertJsonPath('error.message', 'Tool [admins_list_admins] not found.');
            $this->assertNotContains('admins_list_admins', array_column((array) $this->rpc($token, 'tools/list')->json('result.tools'), 'name'));
        }
    }

    #[Test]
    public function a_role_is_made_through_a_key_under_the_rules_of_the_panel(): void
    {
        $manager = $this->admin('roles@example.test');
        $manager->roles()->attach($this->role('role-makers', ['roles.manage', 'admins.view']));
        $this->actingAs($manager, 'cms');

        $token = (string) $this->postJson('/api/cms/auth/keys', $this->input($manager, ['admins:read', 'admins:write']))
            ->assertCreated()
            ->json('token');

        $this->mcp('admins_create_role', $token, ['name' => 'Readers', 'permissions' => ['admins.view']])
            ->assertJsonPath('result.isError', false);
        $this->assertSame(['admins.view'], Role::query()->where('slug', 'readers')->sole()->permissions);

        // Rule 2 through the same door: the answer says why, and nothing is made.
        $refused = $this->mcp('admins_create_role', $token, ['name' => 'Wider', 'permissions' => ['admins.manage']]);
        $this->assertStringContainsString('beyond-yours', (string) $refused->getContent());
        $this->assertFalse(Role::query()->where('slug', 'wider')->exists());
    }

    #[Test]
    public function a_super_administrators_key_is_switched_off_only_by_one_or_by_its_owner(): void
    {
        $owner = $this->admin('owner@example.test', super: true);
        $this->actingAs($this->admin('issuer@example.test', super: true), 'cms');
        $id = (int) $this->postJson('/api/cms/auth/keys', $this->input($owner))->assertCreated()->json('data.id');

        $this->actingAs($this->manager(), 'cms');
        $this->deleteJson("/api/cms/auth/keys/{$id}")
            ->assertForbidden()
            ->assertJsonPath('rule', 'only-super');

        $this->actingAs($owner, 'cms');
        $this->deleteJson("/api/cms/auth/keys/{$id}")->assertOk();
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
    public function nobody_issues_a_key_for_somebody_who_holds_more_than_they_do(): void
    {
        $this->actingAs($this->manager(), 'cms');
        $editor = $this->admin('editor@example.test');
        $editor->roles()->attach($this->role('editors', ['pages.manage']));

        // The key would act as the editor: pages the manager may not touch, in the manager's hands.
        $this->postJson('/api/cms/auth/keys', $this->input($editor))
            ->assertForbidden()
            ->assertJsonPath('rule', 'beyond-yours')
            ->assertJsonPath('missing', ['pages.manage']);

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
        $owner = $this->admin('owner@example.test');
        $owner->roles()->attach($this->role('readers', ['admins.view']));

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
        $manager->roles()->attach($this->role('managers-'.md5($email), ['admins.view', 'admins.manage']));

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
