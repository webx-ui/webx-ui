<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use phpseclib4\Crypt\RSA;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * The queue with Passport switched on and keys to sign with: for the watcher's keys, which are
 * only worth anything once something calls the server with them — and is turned away when the
 * stage has moved on.
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
        // The guard remembers who it let in last; a real request starts with nobody.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/cms/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => (object) $arguments],
        ]);
    }

    /**
     * What a tool answered, when it answered rather than refused.
     *
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>
     */
    protected function answer(TestResponse $response): array
    {
        $response->assertOk()->assertJsonPath('result.isError', false);

        $content = $response->json('result.structuredContent');

        return is_array($content) ? $content : [];
    }

    /**
     * The words of a refusal: a tool's, or the server's for a tool the key does not see.
     *
     * @param  TestResponse<Response>  $response
     */
    protected function refusal(TestResponse $response): string
    {
        // A tool outside the key's scopes is not in its list at all: a protocol error, not a
        // tool's refusal.
        if ($response->json('error') !== null) {
            return (string) $response->json('error.message');
        }

        $response->assertJsonPath('result.isError', true);

        return (string) $response->json('result.content.0.text');
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
