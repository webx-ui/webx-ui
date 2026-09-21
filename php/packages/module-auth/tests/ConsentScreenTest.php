<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use phpseclib4\Crypt\RSA;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Auth\Consent\ConsentScreen;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Grants\Grant;

/**
 * The page a person sees when their agent asks to be let in, and what pressing Allow leaves
 * behind.
 */
final class ConsentScreenTest extends TestCase
{
    private const CALLBACK = 'http://localhost:51999/callback';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PassportServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.panel', ['en', 'ru']);

        [$private, $public] = self::keys();
        $app['config']->set('passport.private_key', $private);
        $app['config']->set('passport.public_key', $public);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Passport::class))->getFileName(), 2).'/database/migrations');

        parent::defineDatabaseMigrations();
    }

    /**
     * @return array{string, string}
     */
    private static function keys(): array
    {
        static $keys = null;

        if ($keys === null) {
            $key = RSA::createKey(2048);

            $keys = [(string) $key, (string) $key->getPublicKey()];
        }

        return $keys;
    }

    #[Test]
    public function a_guest_is_sent_to_the_panel_to_sign_in_and_told_where_to_come_back(): void
    {
        $client = $this->client();

        $location = (string) $this->get($this->authorizeUrl($client))->assertStatus(302)->headers->get('Location');

        $this->assertStringStartsWith('http://localhost/cms/login?next=', $location);

        // The address to come back to is the one asked for, whole — Laravel re-sorts its
        // query on the way through, so it is read back rather than compared as a string.
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertIsString($query['next'] ?? null);
        $this->assertStringStartsWith('http://localhost/oauth/authorize?', $query['next']);
        $this->assertStringContainsString('client_id='.$client, $query['next']);
        $this->assertStringContainsString('code_challenge_method=S256', $query['next']);
    }

    #[Test]
    public function the_screen_says_who_asks_where_the_answer_goes_and_what_the_agent_may_do(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['admins.view', 'admins.manage']));

        $page = $this->actingAs($admin, 'cms')->get($this->authorizeUrl($this->client()))->assertOk();

        $page->assertSee('Claude');
        $page->assertSee('(localhost)');
        $page->assertSee('asks for access to the panel of localhost');
        $page->assertSee('You are signed in as Admin');
        // In the words of the module, not in scopes.
        $page->assertSee('Administrators — view and edit');
        $page->assertSee('You are responsible for what the agent does in your name.');
        $page->assertSee('Read only');
        $page->assertSee('name="auth_token"', false);
        $page->assertSee('The answer goes to localhost');
    }

    #[Test]
    public function a_person_who_may_only_look_is_told_so(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('viewer', ['admins.view']));

        $this->actingAs($admin, 'cms')->get($this->authorizeUrl($this->client()))
            ->assertSee('Administrators — view')
            ->assertDontSee('view and edit');
    }

    #[Test]
    public function a_super_administrator_is_told_everything_rather_than_a_list(): void
    {
        $this->actingAs($this->admin(super: true), 'cms')->get($this->authorizeUrl($this->client()))
            ->assertSee('Everything: you are a super administrator')
            ->assertDontSee('Administrators —');
    }

    #[Test]
    public function the_screen_is_in_the_language_of_the_panel(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['locale' => 'ru'])->save();

        $this->actingAs($admin, 'cms')->get($this->authorizeUrl($this->client()))
            ->assertSee('просит доступ к панели')
            ->assertSee('Только чтение');
    }

    #[Test]
    public function allowing_writes_the_consent_down_and_hands_back_a_code(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['admins.manage']));
        $client = $this->client();

        $response = $this->approve($admin, $client, ['read_only' => '1']);

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(self::CALLBACK.'?', $location);
        $this->assertStringContainsString('code=', $location);

        $grant = Grant::query()->sole();
        $this->assertSame($admin->id, $grant->cms_user_id);
        $this->assertSame($client, $grant->oauth_client_id);
        $this->assertSame('Claude', $grant->client_name);
        $this->assertSame('localhost', $grant->redirect_host);
        $this->assertTrue($grant->read_only);
        $this->assertSame(ConsentScreen::VERSION, $grant->consent_version);
        $this->assertNotNull($grant->created_at);
    }

    #[Test]
    public function allowing_the_same_client_again_updates_the_terms_rather_than_adding_a_row(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->approve($admin, $client, ['read_only' => '1']);
        $this->assertTrue(Grant::query()->sole()->read_only);

        // Passport skips the screen while a token is live; `prompt=consent` asks for it again,
        // the way a client that wants the terms changed would.
        $this->approve($admin, $client, [], '&prompt=consent');

        $this->assertSame(1, Grant::query()->count());
        $this->assertFalse(Grant::query()->sole()->read_only);
    }

    #[Test]
    public function a_form_with_the_wrong_token_writes_nothing(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'cms')->get($this->authorizeUrl($this->client()))->assertOk();

        $this->actingAs($admin, 'cms')
            ->post('/oauth/consent', ['auth_token' => 'not-the-one', 'read_only' => '1'])
            ->assertForbidden();

        $this->assertSame(0, Grant::query()->count());
    }

    #[Test]
    public function signing_in_as_somebody_else_ends_the_session_and_comes_back_through_the_panel(): void
    {
        $admin = $this->admin();
        $url = $this->authorizeUrl($this->client());

        $this->actingAs($admin, 'cms')
            ->post('/oauth/consent/switch', ['next' => $url])
            ->assertRedirect('http://localhost/cms/login?next='.rawurlencode($url));

        $this->assertGuest('cms');

        // An address on another host is not somewhere the sign-in form sends anybody.
        $this->post('/oauth/consent/switch', ['next' => 'https://elsewhere.example/'])
            ->assertRedirect('http://localhost/cms/login');
    }

    /**
     * @param  array<string, string>  $form
     * @return TestResponse<Response>
     */
    private function approve(CmsUser $admin, string $client, array $form, string $extra = ''): TestResponse
    {
        $page = $this->actingAs($admin, 'cms')->get($this->authorizeUrl($client).$extra)->assertOk();

        preg_match('/name="auth_token" value="([^"]+)"/', (string) $page->getContent(), $found);
        $this->assertNotEmpty($found[1] ?? null, 'The consent page carries no auth_token.');

        return $this->actingAs($admin, 'cms')
            ->post('/oauth/consent', ['auth_token' => $found[1], ...$form])
            ->assertStatus(302);
    }

    private function client(): string
    {
        $client = $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => [self::CALLBACK],
        ])->assertCreated()->json('client_id');

        $this->assertIsString($client);

        return $client;
    }

    private function authorizeUrl(string $client): string
    {
        return 'http://localhost/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client,
            'redirect_uri' => self::CALLBACK,
            'scope' => 'mcp:use',
            'state' => 'consent-test',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'verifier-verifier-verifier-verifier-verifier', true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }
}
