<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use Illuminate\Support\Carbon;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Grants\Grant;
use WebxUi\Mcp\Grants\Grants;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Mcp\Tests\Fixtures\PassportUser;
use WebxUi\Mcp\Tests\Fixtures\SeoModule;
use WebxUi\Mcp\Tests\Fixtures\TokenUser;

/**
 * The terms a person set on the consent screen, applied to the calls that come in on the
 * token they granted there.
 */
final class GrantsTest extends TestCase
{
    private const CLIENT = '9d2f6c1e-0a7b-4c3d-8e5f-1a2b3c4d5e6f';

    #[Test]
    public function a_read_only_connection_may_look_and_may_not_change(): void
    {
        $this->register(new SeoModule);
        $this->grant(['read_only' => true]);

        $agent = PassportUser::bearing(['mcp:use'], self::CLIENT);

        WebxServer::actingAs($agent)
            ->tool($this->tool('seo_get_seo'), ['id' => '1'])
            ->assertOk();

        // Refused here rather than by the scope check: the token carries the whole server's
        // scope, and what says no is the person's choice, which is stronger than their rights.
        WebxServer::actingAs($agent)
            ->tool($this->tool('seo_bulk_update_seo'), [])
            ->assertHasErrors(['read-only']);
    }

    #[Test]
    public function a_read_only_connection_is_not_shown_the_tools_it_may_not_use(): void
    {
        $this->register(new SeoModule);
        $this->grant(['read_only' => true]);

        $this->assertSame(['seo_get_seo'], $this->listedTo(PassportUser::bearing(['mcp:use'], self::CLIENT)));

        // And a connection that may write sees everything, so the list is the grant's doing.
        $this->grant(['read_only' => false]);

        $this->assertSame(['seo_get_seo', 'seo_bulk_update_seo'], $this->listedTo(PassportUser::bearing(['mcp:use'], self::CLIENT)));
    }

    #[Test]
    public function a_disconnected_connection_is_refused_everything(): void
    {
        $this->register(new SeoModule);
        $this->grant(['revoked_at' => Carbon::now()]);

        $agent = PassportUser::bearing(['mcp:use'], self::CLIENT);

        WebxServer::actingAs($agent)
            ->tool($this->tool('seo_get_seo'), ['id' => '1'])
            ->assertHasErrors(['disconnected']);

        $this->assertSame([], $this->listedTo($agent));
    }

    #[Test]
    public function a_token_that_names_no_grant_is_left_to_its_scopes(): void
    {
        $this->register(new SeoModule);
        $this->grant(['read_only' => true]);

        // A key issued for a machine names scopes and no client; the grant on file is
        // somebody else's connection and has nothing to say about this one.
        WebxServer::actingAs(new TokenUser(['*']))
            ->tool($this->tool('seo_bulk_update_seo'), [])
            ->assertOk();

        // The same administrator through a client nobody wrote a grant for — a token from
        // before grants were recorded — is not refused for the row that is missing.
        WebxServer::actingAs(PassportUser::bearing(['mcp:use'], '00000000-0000-4000-8000-000000000000'))
            ->tool($this->tool('seo_bulk_update_seo'), [])
            ->assertOk();
    }

    #[Test]
    public function a_call_writes_down_when_the_connection_was_last_used(): void
    {
        $this->register(new SeoModule);
        $grant = $this->grant(['read_only' => false]);

        $this->assertNull($grant->last_used_at);

        Carbon::setTestNow('2026-09-21 10:00:00');

        WebxServer::actingAs(PassportUser::bearing(['mcp:use'], self::CLIENT))
            ->tool($this->tool('seo_get_seo'), ['id' => '1'])
            ->assertOk();

        $this->assertSame('2026-09-21 10:00:00', $grant->fresh()?->last_used_at?->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function grant(array $terms): Grant
    {
        return Grant::query()->updateOrCreate(
            ['cms_user_id' => 1, 'oauth_client_id' => self::CLIENT],
            [
                'client_name' => 'Claude',
                'redirect_host' => 'claude.ai',
                'consent_version' => '2026-09-21',
                'read_only' => false,
                'revoked_at' => null,
                'created_at' => Carbon::now(),
                ...$terms,
            ],
        );
    }

    /**
     * What `tools/list` would show this caller. `laravel/mcp` asks each tool through the
     * container, and the request it resolves is the one bound there — so that is where the
     * caller goes.
     *
     * @return list<string>
     */
    private function listedTo(PassportUser $agent): array
    {
        $this->app->forgetInstance(Grants::class);
        $this->app['request']->setUserResolver(static fn (): PassportUser => $agent);

        $server = $this->app->make(WebxServer::class, ['transport' => new FakeTransporter]);
        $server->start();

        return $server->createContext()->tools()->map(static fn ($tool): string => $tool->name())->values()->all();
    }

    private function tool(string $name): RegistryTool
    {
        return new RegistryTool($this->tools()->tool($name));
    }
}
