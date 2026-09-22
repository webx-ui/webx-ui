<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use Illuminate\Foundation\Application;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use phpseclib4\Crypt\RSA;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WebxUi\Mcp\Tests\Fixtures\SeoModule;

/**
 * The way in for a person who has only an address to paste.
 *
 * `laravel/mcp` writes the protocol and Passport issues the tokens; what is checked here is
 * the part we are responsible for — that both are pointed at the panel rather than at the
 * site, and that neither of the two doors that ship open stayed open.
 */
final class OAuthDoorTest extends TestCase
{
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
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');

        // `php artisan passport:keys` on a site. Without them even a call with no token at
        // all is a 500: the guard is built before it is asked anything, and building it
        // reads the public key.
        [$private, $public] = self::keys();
        $app['config']->set('passport.private_key', $private);
        $app['config']->set('passport.public_key', $public);

        // What `webx-ui/module-auth` registers: the panel's own people, in their own table.
        $app['config']->set('auth.providers.cms_users', ['driver' => 'eloquent', 'model' => Fixtures\PassportUser::class]);
        $app['config']->set('webx-auth.provider', 'cms_users');
    }

    /**
     * One keypair for the whole run: generating an RSA key is the slowest thing in this file.
     *
     * @return array{string, string}
     */
    private static function keys(): array
    {
        static $keys = null;

        if ($keys === null) {
            // The same library `passport:keys` uses, so the keys are the ones a site gets —
            // and no dependency on the openssl extension being configured on this machine.
            $key = RSA::createKey(2048);

            $keys = [(string) $key, (string) $key->getPublicKey()];
        }

        return $keys;
    }

    #[Test]
    public function a_call_without_a_token_is_told_where_to_get_one(): void
    {
        $this->register(new SeoModule);

        $response = $this->postJson('/api/cms/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => [],
        ])->assertUnauthorized();

        // Not a bare 401: the header is the whole discovery chain for a client that has been
        // given nothing but the address.
        $this->assertStringContainsString(
            'resource_metadata="'.url('/.well-known/oauth-protected-resource/api/cms/mcp').'"',
            (string) $response->headers->get('WWW-Authenticate'),
        );

        $this->getJson('/.well-known/oauth-protected-resource/api/cms/mcp')
            ->assertOk()
            ->assertJsonPath('authorization_servers.0', url('/'))
            ->assertJsonPath('scopes_supported.0', 'mcp:use');

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256');
    }

    #[Test]
    public function a_client_may_only_be_sent_back_to_a_listed_address(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Not Claude',
            'redirect_uris' => ['https://collector.example/callback'],
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');

        // A scheme of its own is the same question asked differently, and gets the same answer.
        $this->postJson('/oauth/register', [
            'client_name' => 'Not Cursor',
            'redirect_uris' => ['sketchy://callback'],
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');

        $permitted = [
            'https://claude.ai/api/mcp/auth_callback',
            'https://claude.com/api/mcp/auth_callback',
            'https://chatgpt.com/connector_platform_oauth_redirect',
            'https://chat.openai.com/oauth/callback',
            'http://localhost:53535/callback',
            'cursor://anysphere.cursor-retrieval/oauth/callback',
        ];

        foreach ($permitted as $uri) {
            $this->postJson('/oauth/register', ['client_name' => 'A client', 'redirect_uris' => [$uri]])
                ->assertCreated()
                ->assertJsonPath('scope', 'mcp:use');
        }
    }

    #[Test]
    public function registration_is_rate_limited(): void
    {
        // Nobody has signed in yet when a client introduces itself, so there is nobody to
        // ask whether it may — which leaves the clients table growing from whoever finds
        // the address.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/oauth/register', ['redirect_uris' => ['https://collector.example/callback']])
                ->assertStatus(400);
        }

        // A permitted address on the eleventh, so it is the limiter refusing and not the list.
        $this->postJson('/oauth/register', ['redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])
            ->assertStatus(429);
    }

    #[Test]
    public function the_oauth_endpoints_count_on_their_own(): void
    {
        // A bare `throttle` is counted per address across every throttled route in the
        // application, and the lowest limit among them decides — so left alone, a stranger
        // registering clients spends the allowance of the panel's sign-in form.
        $middleware = collect($this->app->make('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array($route->uri(), ['oauth/register', 'oauth/token'], true)
                && in_array('POST', $route->methods(), true))
            ->mapWithKeys(fn ($route): array => [$route->uri() => $route->middleware()]);

        $this->assertContains('throttle:webx-mcp-register', $middleware['oauth/register']);
        $this->assertContains('throttle:webx-mcp-token', $middleware['oauth/token']);
        $this->assertNotContains('throttle', $middleware['oauth/token']);
    }

    #[Test]
    public function passport_asks_the_panel_who_is_signing_in(): void
    {
        // Passport's own default is the site's visitors. Left at that, an administrator
        // arriving at the consent screen is shown a login form for an account the panel has
        // never heard of, and no amount of signing in gets them past it.
        $this->assertSame('cms', config('passport.guard'));

        // And the guard has to be in place before Passport boots: it bakes the value into
        // the middleware of the approve step. Read too late, the consent page shows the
        // right administrator and the button under it says nobody is signed in.
        $approve = collect($this->app->make('router')->getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->getName() === 'passport.authorizations.approve');

        $this->assertContains('auth:cms', $approve?->middleware() ?? []);

        $this->assertSame(
            ['driver' => 'passport', 'provider' => 'cms_users'],
            config('auth.guards.api'),
        );

        $this->assertSame(1, Passport::tokensExpireIn()->h);
        $this->assertSame(30, Passport::refreshTokensExpireIn()->d);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Passport only publishes its migrations; an application runs them once. The tests
        // are the application here, and a registering client needs somewhere to be written.
        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Passport::class))->getFileName(), 2).'/database/migrations');

        $this->artisan('migrate')->run();
    }
}
