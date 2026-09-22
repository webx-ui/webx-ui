<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\AbstractModule;
use WebxUi\Mcp\Calls\Call;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Grants\Grant;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Mcp\Tests\Fixtures\Administrator;
use WebxUi\Mcp\Tests\Fixtures\PassportUser;
use WebxUi\Mcp\Tests\Fixtures\SeoModule;
use WebxUi\Mcp\Tests\Fixtures\TokenUser;
use WebxUi\Mcp\Tool;

/**
 * The trail of what agents did: every call, whichever way it went.
 */
final class CallLogTest extends TestCase
{
    private const CLIENT = '9d2f6c1e-0a7b-4c3d-8e5f-1a2b3c4d5e6f';

    #[Test]
    public function an_answered_call_is_written_down_with_who_what_and_how_long(): void
    {
        $this->register(new SeoModule);

        WebxServer::actingAs(new Administrator(['seo.view']))
            ->tool($this->tool('seo_get_seo'), ['id' => '7'])
            ->assertOk();

        $call = Call::query()->sole();

        $this->assertSame(3, $call->cms_user_id);
        $this->assertNull($call->grant_id);
        $this->assertSame('seo_get_seo', $call->tool);
        $this->assertTrue($call->ok);
        $this->assertNull($call->error);
        $this->assertFalse($call->dry_run);
        $this->assertGreaterThanOrEqual(0, $call->duration_ms);
        $this->assertNotNull($call->created_at);
        $this->assertSame(['id' => '7'], json_decode((string) $call->arguments, true));
    }

    #[Test]
    public function a_dry_run_is_marked_as_one(): void
    {
        $this->register(new SeoModule);

        WebxServer::tool($this->tool('seo_bulk_update_seo'), ['dry_run' => true])->assertOk();
        WebxServer::tool($this->tool('seo_bulk_update_seo'), [])->assertOk();

        $this->assertSame(
            [true, false],
            Call::query()->orderBy('id')->pluck('dry_run')->all(),
        );

        // `dry_run` means nothing to a tool that only looks, and is not a dry run of one.
        WebxServer::tool($this->tool('seo_get_seo'), ['id' => '1', 'dry_run' => true])->assertOk();

        $this->assertFalse(Call::query()->latest('id')->firstOrFail()->dry_run);
    }

    #[Test]
    public function a_refusal_at_the_door_is_a_row_with_the_reason(): void
    {
        $this->register(new SeoModule);

        WebxServer::actingAs(new TokenUser(['seo:read']))
            ->tool($this->tool('seo_bulk_update_seo'), [])
            ->assertHasErrors(['[seo:write]']);

        WebxServer::actingAs(new Administrator(['blog.articles.view']))
            ->tool($this->tool('seo_get_seo'), ['id' => '1'])
            ->assertHasErrors(['[seo.view] or [seo.manage]']);

        $calls = Call::query()->orderBy('id')->get();

        $this->assertCount(2, $calls);
        $this->assertFalse($calls[0]->ok);
        $this->assertStringContainsString('[seo:write]', (string) $calls[0]->error);
        $this->assertFalse($calls[1]->ok);
        $this->assertStringContainsString('[seo.view] or [seo.manage]', (string) $calls[1]->error);
    }

    #[Test]
    public function a_call_on_a_connection_names_it(): void
    {
        $this->register(new SeoModule);

        Grant::query()->create([
            'cms_user_id' => 1,
            'oauth_client_id' => self::CLIENT,
            'client_name' => 'Claude',
            'redirect_host' => 'claude.ai',
            'read_only' => true,
            'consent_version' => '2026-09-21',
            'created_at' => Carbon::now(),
        ]);

        $agent = PassportUser::bearing(['mcp:use'], self::CLIENT);

        WebxServer::actingAs($agent)->tool($this->tool('seo_get_seo'), ['id' => '1'])->assertOk();
        WebxServer::actingAs($agent)->tool($this->tool('seo_bulk_update_seo'), [])->assertHasErrors(['read-only']);

        $calls = Call::query()->with('grant')->orderBy('id')->get();

        $this->assertSame([1, 1], $calls->pluck('cms_user_id')->all());
        $this->assertSame(['Claude', 'Claude'], $calls->pluck('grant.client_name')->all());
        $this->assertSame([true, false], $calls->pluck('ok')->all());
    }

    #[Test]
    public function what_the_handler_refused_or_threw_is_written_down_too(): void
    {
        $this->register(new class extends AbstractModule implements ProvidesMcpTools
        {
            use ProvidesMcpDefaults;

            public function id(): string
            {
                return 'library';
            }

            /**
             * @return list<Tool>
             */
            public function mcpTools(): array
            {
                return [
                    Tool::read('borrow', 'Borrow a book.', static fn (): never => throw new ToolFailure('The book is out.')),
                    Tool::read('burn', 'Burn a book.', static fn (): never => throw new RuntimeException('No matches.')),
                    Tool::mutating('lend', 'Lend a book.', static fn (): array => ['ok' => false, 'reason' => 'Nobody by that name.']),
                ];
            }
        });

        WebxServer::tool($this->tool('library_borrow'))->assertHasErrors(['The book is out.']);

        // A handler's own `ok: false` is a success to the protocol and a failure to the person
        // reading the log, so the log takes the handler's word for it.
        WebxServer::tool($this->tool('library_lend'), ['name' => 'Anna'])->assertOk();

        // Thrown on after being written down; what the transport makes of it is its business.
        WebxServer::tool($this->tool('library_burn'));

        $calls = Call::query()->orderBy('id')->get();

        $this->assertSame(['library_borrow', 'library_lend', 'library_burn'], $calls->pluck('tool')->all());
        $this->assertSame([false, false, false], $calls->pluck('ok')->all());
        $this->assertSame(
            ['The book is out.', 'Nobody by that name.', 'RuntimeException: No matches.'],
            $calls->pluck('error')->all(),
        );
    }

    #[Test]
    public function a_secret_never_lands_in_the_log_and_long_arguments_are_cut(): void
    {
        $this->app['config']->set('webx-mcp.calls.arguments_length', 120);

        $this->register(new class extends AbstractModule implements ProvidesMcpTools
        {
            use ProvidesMcpDefaults;

            public function id(): string
            {
                return 'vault';
            }

            /**
             * @return list<Tool>
             */
            public function mcpTools(): array
            {
                return [
                    Tool::mutating('open', 'Open the vault.', static fn (): array => ['opened' => true]),
                ];
            }
        });

        WebxServer::tool($this->tool('vault_open'), [
            'password' => 'hunter2',
            'nested' => ['api_key' => 'sk-live-1', 'title' => 'Kept'],
            'body' => str_repeat('a', 500),
        ])->assertOk();

        $arguments = (string) Call::query()->sole()->arguments;

        $this->assertStringNotContainsString('hunter2', $arguments);
        $this->assertStringNotContainsString('sk-live-1', $arguments);
        $this->assertStringContainsString('[redacted]', $arguments);
        $this->assertStringContainsString('Kept', $arguments);
        $this->assertSame(121, mb_strlen($arguments));
        $this->assertStringEndsWith('…', $arguments);
    }

    #[Test]
    public function the_log_can_be_switched_off(): void
    {
        $this->app['config']->set('webx-mcp.calls.enabled', false);
        $this->register(new SeoModule);

        WebxServer::tool($this->tool('seo_get_seo'), ['id' => '1'])->assertOk();

        $this->assertSame(0, Call::query()->count());
    }

    #[Test]
    public function old_rows_are_pruned_by_days_and_the_rest_kept(): void
    {
        $this->app['config']->set('webx-mcp.calls.days', 30);

        Call::query()->create(['tool' => 'old', 'ok' => true, 'created_at' => Carbon::now()->subDays(31)]);
        Call::query()->create(['tool' => 'recent', 'ok' => true, 'created_at' => Carbon::now()->subDays(29)]);

        $this->artisan('webx:mcp:prune-calls')
            ->expectsOutputToContain('Removed 1 call older than 30 days.')
            ->assertSuccessful();

        $this->assertSame(['recent'], Call::query()->pluck('tool')->all());

        $this->app['config']->set('webx-mcp.calls.days', null);

        $this->artisan('webx:mcp:prune-calls')
            ->expectsOutputToContain('kept forever')
            ->assertSuccessful();

        $this->assertSame(1, Call::query()->count());
    }

    private function tool(string $name): RegistryTool
    {
        return new RegistryTool($this->tools()->tool($name));
    }
}
