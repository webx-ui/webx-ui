<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use Illuminate\Auth\GenericUser;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AbstractModule;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Server\RegistryPrompt;
use WebxUi\Mcp\Server\RegistryResource;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Mcp\Tests\Fixtures\MediaLibraryModule;
use WebxUi\Mcp\Tests\Fixtures\SeoModule;
use WebxUi\Mcp\Tests\Fixtures\TokenUser;
use WebxUi\Mcp\Tool;

/**
 * The transport: what the modules declared, served the way `laravel/mcp` serves it.
 */
final class WebxServerTest extends TestCase
{
    #[Test]
    public function the_server_offers_what_the_modules_offer(): void
    {
        $this->register(new SeoModule, new MediaLibraryModule);

        $context = $this->server()->createContext();

        $this->assertSame(
            ['media_library_find_unused', 'seo_get_seo', 'seo_bulk_update_seo'],
            $context->tools()->map(static fn ($tool): string => $tool->name())->values()->all(),
        );

        $tools = $context->tools()->keyBy(static fn ($tool): string => $tool->name());

        $bulk = $tools['seo_bulk_update_seo']->toArray();
        $this->assertArrayHasKey('dry_run', $bulk['inputSchema']['properties']);
        $this->assertFalse($bulk['annotations']['readOnlyHint']);
        $this->assertSame('Bulk Update Seo', $bulk['title']);

        $get = $tools['seo_get_seo']->toArray();
        $this->assertTrue($get['annotations']['readOnlyHint']);
        $this->assertSame(['id'], $get['inputSchema']['required']);

        $this->assertSame(['seo://audit/missing-title'], $context->resources()->map(static fn ($resource): string => $resource->uri())->values()->all());
        $this->assertSame(['suggest_titles'], $context->prompts()->map(static fn ($prompt): string => $prompt->name())->values()->all());
    }

    #[Test]
    public function an_empty_schema_still_goes_out_as_an_object(): void
    {
        $this->register(new MediaLibraryModule);

        $tool = $this->server()->createContext()->tools()->first();
        $json = json_encode($tool->toArray(), JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"properties":{}', $json);
    }

    #[Test]
    public function a_call_runs_the_module_handler_with_its_arguments(): void
    {
        $this->register(new SeoModule);

        WebxServer::tool($this->tool('seo_get_seo'), ['id' => '7'])
            ->assertOk()
            ->assertStructuredContent(['title' => 'Example', 'for' => '7']);

        WebxServer::tool($this->tool('seo_bulk_update_seo'), ['dry_run' => true])
            ->assertOk()
            ->assertSee('"changed":0');

        WebxServer::tool($this->tool('seo_bulk_update_seo'), [])
            ->assertOk()
            ->assertSee('"changed":12');
    }

    #[Test]
    public function a_token_without_the_scope_is_refused_before_the_handler_runs(): void
    {
        $this->register(new SeoModule);

        $reader = new TokenUser(['seo:read']);

        WebxServer::actingAs($reader)
            ->tool($this->tool('seo_bulk_update_seo'), [])
            ->assertHasErrors(['[seo:write]']);

        WebxServer::actingAs($reader)
            ->tool($this->tool('seo_get_seo'), ['id' => '1'])
            ->assertOk();

        WebxServer::actingAs(new TokenUser(['*']))
            ->tool($this->tool('seo_bulk_update_seo'), [])
            ->assertOk();
    }

    #[Test]
    public function a_user_without_a_token_is_not_asked_for_a_scope(): void
    {
        $this->register(new SeoModule);

        WebxServer::actingAs(new GenericUser(['id' => 2]))
            ->tool($this->tool('seo_bulk_update_seo'), [])
            ->assertOk();
    }

    #[Test]
    public function a_tool_failure_reaches_the_agent_as_an_error_with_its_message(): void
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
                ];
            }
        });

        WebxServer::tool($this->tool('library_borrow'))->assertHasErrors(['The book is out.']);
    }

    #[Test]
    public function resources_and_prompts_answer(): void
    {
        $this->register(new SeoModule);

        WebxServer::resource(new RegistryResource($this->tools()->resources()[0]))
            ->assertOk()
            ->assertSee('[]');

        WebxServer::prompt(new RegistryPrompt($this->tools()->prompts()[0]), ['section' => 'news'])
            ->assertOk()
            ->assertSee('Draft a title');
    }

    #[Test]
    public function the_http_endpoint_is_closed_to_strangers_and_answers_a_user(): void
    {
        $this->register(new SeoModule);

        // The middleware reads the guard at request time; `web` lets the test sign in
        // without Sanctum, which this package does not depend on.
        $this->app['config']->set('webx-mcp.guard', 'web');

        $this->postJson('/api/cms/mcp', $this->rpc('tools/list'))->assertUnauthorized();

        $this->actingAs(new GenericUser(['id' => 1]), 'web')
            ->postJson('/api/cms/mcp', $this->rpc('tools/list'))
            ->assertOk()
            ->assertJsonPath('result.tools.0.name', 'seo_get_seo');

        $this->actingAs(new GenericUser(['id' => 1]), 'web')
            ->postJson('/api/cms/mcp', $this->rpc('tools/call', ['name' => 'seo_get_seo', 'arguments' => ['id' => '3']]))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.for', '3');
    }

    private function server(): WebxServer
    {
        $server = $this->app->make(WebxServer::class, ['transport' => new FakeTransporter]);
        $server->start();

        return $server;
    }

    private function tool(string $name): RegistryTool
    {
        return new RegistryTool($this->tools()->tool($name));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params = []): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];
    }
}
