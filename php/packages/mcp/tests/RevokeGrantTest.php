<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WebxUi\Mcp\Grants\Grant;
use WebxUi\Mcp\Grants\Grants;

/**
 * Disconnecting an agent, which is two things at once: the row says the connection ended, and
 * the tokens behind it stop working.
 *
 * The second half is the one worth a test. An access token lives an hour and would be missed
 * by nobody; the refresh token behind it is a month, and a revocation that leaves it alone
 * leaves the agent connected for that month with a panel that says it is not.
 */
final class RevokeGrantTest extends TestCase
{
    private const CLIENT = '9d2f6c1e-0a7b-4c3d-8e5f-1a2b3c4d5e6f';

    #[Test]
    public function ending_a_connection_kills_both_kinds_of_token(): void
    {
        $grant = $this->grant();

        $this->token('live-one', self::CLIENT, 7);
        $this->token('live-two', self::CLIENT, 7);
        // Another client of the same person, and the same client of somebody else: neither
        // was disconnected, and both have to survive.
        $other = $this->token('other-client', '00000000-0000-4000-8000-000000000000', 7);
        $somebody = $this->token('other-person', self::CLIENT, 8);

        $this->app->make(Grants::class)->revoke($grant);

        $this->assertNotNull($grant->refresh()->revoked_at);

        foreach (['live-one', 'live-two'] as $id) {
            $this->assertTrue($this->revoked($id), "[{$id}] is still live.");
            $this->assertTrue(
                (bool) RefreshToken::query()->where('access_token_id', $id)->value('revoked'),
                "The refresh token behind [{$id}] is still live.",
            );
        }

        $this->assertFalse($this->revoked($other));
        $this->assertFalse($this->revoked($somebody));
        $this->assertFalse((bool) RefreshToken::query()->where('access_token_id', $other)->value('revoked'));
    }

    #[Test]
    public function ending_a_connection_twice_keeps_the_first_answer(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $grant = $this->grant();
        $grants = $this->app->make(Grants::class);

        $grants->revoke($grant);

        Carbon::setTestNow('2026-09-22 10:00:00');
        $grants->revoke($grant->refresh());

        $this->assertSame('2026-09-21 10:00:00', $grant->refresh()->revoked_at?->toDateTimeString());

        Carbon::setTestNow();
    }

    /** Read off the table rather than the model: the column is not a declared property. */
    private function revoked(string $id): bool
    {
        return (bool) Token::query()->whereKey($id)->value('revoked');
    }

    private function grant(): Grant
    {
        return Grant::query()->create([
            'cms_user_id' => 7,
            'oauth_client_id' => self::CLIENT,
            'client_name' => 'Claude',
            'redirect_host' => 'claude.ai',
            'read_only' => false,
            'consent_version' => '2026-09-21',
            'created_at' => Carbon::now(),
        ]);
    }

    /** An access token with a refresh token behind it, the pair Passport issues. */
    private function token(string $id, string $client, int $user): string
    {
        Token::query()->create([
            'id' => $id,
            'user_id' => $user,
            'client_id' => $client,
            'scopes' => ['mcp:use'],
            'revoked' => false,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        RefreshToken::query()->create([
            'id' => "refresh-{$id}",
            'access_token_id' => $id,
            'revoked' => false,
            'expires_at' => Carbon::now()->addMonth(),
        ]);

        return $id;
    }

    protected function defineDatabaseMigrations(): void
    {
        // Passport only publishes its migrations; the tests are the application here.
        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Passport::class))->getFileName(), 2).'/database/migrations');

        $this->artisan('migrate')->run();
    }
}
