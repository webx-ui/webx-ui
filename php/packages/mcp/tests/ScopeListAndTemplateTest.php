<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use Laravel\Mcp\Server\Transport\FakeTransporter;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AbstractModule;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Server\RegistryResource;
use WebxUi\Mcp\Server\RegistryResourceTemplate;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Mcp\Tests\Fixtures\TokenUser;
use WebxUi\Mcp\Tool;

/**
 * A tool two kinds of key may use, and a resource read by an address with a variable in it.
 */
final class ScopeListAndTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->register(new class extends AbstractModule implements ProvidesMcpTools
        {
            use ProvidesMcpDefaults;

            public function id(): string
            {
                return 'queue';
            }

            /**
             * @return list<Tool>
             */
            public function mcpTools(): array
            {
                return [
                    Tool::mutating('note', 'Leave a note.', static fn (): array => ['ok' => true], scope: ['queue:write', 'queue:work']),
                ];
            }

            /**
             * @return list<McpResource>
             */
            public function mcpResources(): array
            {
                return [
                    new McpResource('queue://all', 'All', 'Everything.', static fn (): array => ['all' => true]),
                    new McpResource('queue://projects/{slug}', 'Project', 'One project.', static fn (array $arguments): array => ['slug' => $arguments['slug'] ?? null]),
                ];
            }
        });
    }

    #[Test]
    public function any_one_of_the_scopes_lets_a_token_in_and_none_of_them_does_not(): void
    {
        $tool = new RegistryTool($this->tools()->tool('queue_note'));

        WebxServer::actingAs(new TokenUser(['queue:write']))->tool($tool)->assertOk();
        WebxServer::actingAs(new TokenUser(['queue:work']))->tool($tool)->assertOk();
        WebxServer::actingAs(new TokenUser(['queue:read']))->tool($tool)
            ->assertHasErrors(['[queue:write] or [queue:work]']);

        $this->assertSame(['cache:write', 'history:read', 'queue:work', 'queue:write'], $this->tools()->scopes());
        $this->assertSame('queue:write | queue:work', $this->tools()->tool('queue_note')->scope());
    }

    #[Test]
    public function an_address_with_a_variable_is_served_as_a_template(): void
    {
        // After the frame's own `history://types`.
        [$all, $project] = array_map(RegistryResource::for(...), array_slice($this->tools()->resources(), 1));

        $this->assertNotInstanceOf(RegistryResourceTemplate::class, $all);
        $this->assertInstanceOf(RegistryResourceTemplate::class, $project);

        $server = $this->app->make(WebxServer::class, ['transport' => new FakeTransporter]);
        $server->start();
        $context = $server->createContext();

        $this->assertSame(['history://types', 'queue://all'], $context->resources()->map(static fn ($resource): string => $resource->uri())->values()->all());
        $this->assertSame(['queue://projects/{slug}'], $context->resourceTemplates()->map(static fn ($resource): string => $resource->uri())->values()->all());

        WebxServer::resource($project, ['slug' => 'shop'])->assertOk()->assertSee('"slug": "shop"');
    }
}
