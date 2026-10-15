<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Auth\Models\CmsUser;

/**
 * The panel on a host whose web server lets through nothing but GET and POST.
 *
 * `createAdmin({ methodOverride })` sends PUT, PATCH and DELETE as POST and names the real
 * method in `X-HTTP-Method-Override` or in a `_method` field. Nothing on this side was written
 * for it — the framework already reads both — so what these tests hold is that it stays so:
 * the panel's routes answer the method that was meant, and the CSRF check, which the test
 * environment otherwise skips, still guards them.
 */
final class MethodOverrideTest extends TestCase
{
    private const TOKEN = 'a-session-token-of-forty-characters-long';

    protected function setUp(): void
    {
        parent::setUp();

        // The real check: the framework waves every request through while unit tests run.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function modes(): iterable
    {
        yield 'header' => ['header'];
        yield 'field' => ['field'];
    }

    #[Test]
    #[DataProvider('modes')]
    public function put_patch_and_delete_reach_their_routes_through_a_post(string $mode): void
    {
        $me = $this->signedIn();
        $anna = $this->admin('anna@example.test');

        $this->overridden($mode, 'PUT', 'api/cms/auth/me', ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
        $this->assertSame('Renamed', $me->refresh()->name);

        $this->overridden($mode, 'PATCH', "api/cms/auth/admins/{$anna->id}", ['name' => 'Anna Smith', 'email' => 'anna@example.test'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Anna Smith');

        $this->overridden($mode, 'DELETE', "api/cms/auth/admins/{$anna->id}")->assertOk();
        $this->assertSame(0, CmsUser::query()->whereKey($anna->id)->count());
    }

    #[Test]
    #[DataProvider('modes')]
    public function the_csrf_check_still_guards_an_overridden_method(string $mode): void
    {
        $this->signedIn();
        $anna = $this->admin('anna@example.test');

        $this->overridden($mode, 'DELETE', "api/cms/auth/admins/{$anna->id}", token: false)
            ->assertStatus(419);

        $this->assertSame(1, CmsUser::query()->whereKey($anna->id)->count());
    }

    #[Test]
    public function a_plain_post_is_not_mistaken_for_a_put(): void
    {
        // Without the override the route is a PUT one and a POST is not it: what the option
        // changes is only what the request says it is.
        $this->signedIn();

        $this->json('POST', 'api/cms/auth/me', ['name' => 'Renamed'], $this->csrf())
            ->assertStatus(405);
    }

    private function signedIn(): CmsUser
    {
        $me = $this->admin(super: true);
        $this->actingAs($me, 'cms');
        $this->withSession(['_token' => self::TOKEN]);

        return $me;
    }

    /**
     * What `createHttp` puts on the wire for one of the two modes.
     *
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function overridden(string $mode, string $method, string $uri, array $body = [], bool $token = true): TestResponse
    {
        $headers = $token ? $this->csrf() : [];

        if ($mode === 'header') {
            $headers['X-HTTP-Method-Override'] = $method;
        } else {
            $body['_method'] = $method;
        }

        return $this->json('POST', $uri, $body, $headers);
    }

    /**
     * The token as the panel sends it: the `XSRF-TOKEN` cookie read back, encrypted as the
     * framework wrote it.
     *
     * @return array<string, string>
     */
    private function csrf(): array
    {
        $encrypter = $this->app->make('encrypter');
        $value = CookieValuePrefix::create('XSRF-TOKEN', $encrypter->getKey()).self::TOKEN;

        return ['X-XSRF-TOKEN' => $encrypter->encrypt($value, PreventRequestForgery::serialized())];
    }
}
