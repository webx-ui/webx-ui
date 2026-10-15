<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Token;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Keys\Key;

/**
 * `webx:mcp:token`: a key for a program, and the server it opens.
 */
final class McpTokenCommandTest extends PassportTestCase
{
    #[Test]
    public function without_scopes_the_token_reaches_what_the_administrator_may(): void
    {
        $this->admin('owner@example.test', super: true);

        $issued = $this->issue(['--name' => 'platform', '--json' => true]);

        $this->assertSame('Bearer', $issued['type']);
        // The scope an OAuth connection gets: the administrator's permissions decide, and a
        // module installed tomorrow is reachable without a new token.
        $this->assertSame(['mcp:use'], $issued['scopes']);
        // A personal token's lifetime, not the hour an OAuth access token gets: a script has
        // nothing to refresh it with.
        $this->assertNotNull($issued['expires_at']);
        $this->assertTrue(now()->addDays(300)->lt($issued['expires_at']));

        $this->mcp('admins_list_admins', $issued['token'])
            ->assertOk()
            ->assertJsonPath('result.isError', false);
    }

    #[Test]
    public function the_lifetime_is_the_one_asked_for_and_the_key_is_listed(): void
    {
        $this->admin('owner@example.test', super: true);

        $issued = $this->issue(['--name' => 'watcher', '--days' => 3650, '--json' => true]);

        // Signed into the token, not only written beside it: ten years from now, give or take
        // the second the command took.
        $this->assertTrue(now()->addDays(3649)->lt($issued['expires_at']));
        $this->assertTrue(now()->addDays(3651)->gt($issued['expires_at']));
        $this->mcp('admins_list_admins', $issued['token'])->assertJsonPath('result.isError', false);

        $key = Key::query()->sole();
        $this->assertSame('watcher', $key->name);
        $this->assertSame(['mcp:use'], $key->scopes);

        $this->assertSame(1, Artisan::call('webx:mcp:token', ['--name' => 'watcher', '--days' => 3651]));
        $this->assertSame(1, Artisan::call('webx:mcp:token', ['--name' => 'watcher', '--days' => 0]));
        $this->assertSame(1, Key::query()->count());
    }

    #[Test]
    public function all_means_the_same_as_nothing(): void
    {
        $this->admin('owner@example.test', super: true);

        $this->assertSame(['mcp:use'], $this->issue(['--name' => 'platform', '--scopes' => 'all', '--json' => true])['scopes']);
    }

    #[Test]
    public function named_scopes_are_held_to(): void
    {
        $this->admin('owner@example.test', super: true);

        $issued = $this->issue(['--name' => 'reader', '--scopes' => 'admins:read', '--json' => true]);

        $this->assertSame(['admins:read'], $issued['scopes']);

        $this->mcp('admins_list_admins', $issued['token'])->assertJsonPath('result.isError', false);

        // Out of scope is out of the list, and a tool not in the list is not there to call.
        $this->mcp('admins_grant_role', $issued['token'], ['email' => 'owner@example.test', 'role' => 'editors'])
            ->assertBadRequest()
            ->assertJsonPath('error.message', 'Tool [admins_grant_role] not found.');
    }

    #[Test]
    public function a_scope_no_module_declares_is_refused_and_nothing_is_issued(): void
    {
        $this->admin('owner@example.test', super: true);

        $this->assertSame(1, Artisan::call('webx:mcp:token', ['--name' => 'platform', '--scopes' => 'admins:read,pages:write']));
        $this->assertStringContainsString('pages:write', Artisan::output());
        $this->assertSame(0, Token::query()->count());
    }

    #[Test]
    public function without_json_only_the_token_is_printed(): void
    {
        $this->admin('owner@example.test', super: true);

        $this->assertSame(0, Artisan::call('webx:mcp:token', ['--name' => 'platform']));

        $token = trim(Artisan::output());

        // One line a script can put in a header as it is.
        $this->assertStringNotContainsString("\n", $token);
        $this->mcp('admins_list_admins', $token)->assertJsonPath('result.isError', false);
    }

    #[Test]
    public function the_token_belongs_to_the_first_super_administrator_unless_one_is_named(): void
    {
        $this->admin('editor@example.test');
        $this->admin('retired@example.test', super: true, active: false);
        $owner = $this->admin('owner@example.test', super: true);
        $editor = $this->admin('second-editor@example.test');

        $this->issue(['--name' => 'platform']);
        $this->assertSame(1, Token::query()->where('user_id', $owner->getKey())->count());

        $this->issue(['--name' => 'platform', '--admin' => 'second-editor@example.test']);
        $this->assertSame(1, Token::query()->where('user_id', $editor->getKey())->count());
    }

    #[Test]
    public function an_administrator_who_cannot_sign_in_gets_no_token(): void
    {
        $this->admin('retired@example.test', super: true, active: false);

        // Nobody active and super: the switched-off one is not a fallback.
        $this->assertSame(1, Artisan::call('webx:mcp:token', ['--name' => 'platform']));
        $this->assertSame(1, Artisan::call('webx:mcp:token', ['--name' => 'platform', '--admin' => 'retired@example.test']));
        $this->assertSame(1, Artisan::call('webx:mcp:token', ['--name' => 'platform', '--admin' => 'nobody@example.test']));
        $this->assertSame(0, Token::query()->count());
    }

    #[Test]
    public function a_name_is_required(): void
    {
        $this->admin('owner@example.test', super: true);

        $this->assertSame(1, Artisan::call('webx:mcp:token'));
        $this->assertSame(0, Token::query()->count());
    }

    #[Test]
    public function every_call_issues_a_new_token_and_revoke_existing_ends_the_old_ones(): void
    {
        $this->admin('owner@example.test', super: true);

        $first = $this->issue(['--name' => 'platform', '--json' => true])['token'];
        $second = $this->issue(['--name' => 'platform', '--json' => true])['token'];
        $other = $this->issue(['--name' => 'backup', '--json' => true])['token'];

        $this->assertNotSame($first, $second);
        $this->assertSame(3, Token::query()->where('revoked', false)->count());

        $third = $this->issue(['--name' => 'platform', '--revoke-existing' => true, '--json' => true])['token'];

        $this->assertSame(2, Token::query()->where('name', 'platform')->where('revoked', true)->count());

        $this->mcp('admins_list_admins', $first)->assertUnauthorized();
        $this->mcp('admins_list_admins', $third)->assertJsonPath('result.isError', false);
        // A token of another name is somebody else's business.
        $this->mcp('admins_list_admins', $other)->assertJsonPath('result.isError', false);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{token: string, type: string, scopes: list<string>, expires_at: ?string}
     */
    private function issue(array $options): array
    {
        $code = Artisan::call('webx:mcp:token', $options);
        // Read once: the buffer empties as it is read.
        $output = Artisan::output();

        $this->assertSame(0, $code, $output);

        /** @var array{token: string, type: string, scopes: list<string>, expires_at: ?string} */
        return (array) json_decode($output, true);
    }
}
