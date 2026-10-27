<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use phpseclib4\Crypt\RSA;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * A panel with Passport switched on and keys to sign with, for tests that issue real tokens and
 * call the server with them.
 *
 * The tokens are used, not only counted. A personal token that Passport issues happily and the
 * door then turns away — wrong provider, a scope nobody registered, a client of the wrong kind,
 * a lifetime that did not stick — looks exactly like a working one until something calls with it.
 */
abstract class PassportTestCase extends TestCase
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
        parent::defineEnvironment($app);

        [$private, $public] = self::keys();
        $app['config']->set('passport.private_key', $private);
        $app['config']->set('passport.public_key', $public);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->app->make('migrator')->path(dirname((string) (new ReflectionClass(Passport::class))->getFileName(), 2).'/database/migrations');

        parent::defineDatabaseMigrations();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return TestResponse<Response>
     */
    protected function mcp(string $tool, string $token, array $arguments = []): TestResponse
    {
        return $this->rpc($token, 'tools/call', ['name' => $tool, 'arguments' => (object) $arguments]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    protected function rpc(string $token, string $method, array $params = []): TestResponse
    {
        // The guard remembers who it let in last; a real request starts with nobody.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/cms/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => (object) $params,
        ]);
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
}
