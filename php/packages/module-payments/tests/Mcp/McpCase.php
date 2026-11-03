<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Mcp;

use Illuminate\Foundation\Application;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Payments\Tests\Panel\PanelCase;

/**
 * The section by its other door (spec §16): the panel's providers and the MCP server, a tool
 * called as an agent calls it — through the server, as an administrator. Every answer is kept, so
 * a test can say no key of the gateway is in any of them.
 */
abstract class McpCase extends PanelCase
{
    /** Everything the tools answered in this test, as JSON. */
    protected string $answered = '';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $permissions
     */
    protected function agent(string $tool, array $arguments = [], array $permissions = ['payments.view', 'payments.manage', 'payments.exchange', 'orders.view', 'orders.manage']): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
        $user = $this->signIn($permissions);

        return WebxServer::actingAs(CmsUser::query()->findOrFail($user->getKey()), 'cms')->tool($bound, $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>|null  $permissions
     * @return array<string, mixed>
     */
    protected function use(string $tool, array $arguments = [], ?array $permissions = null): array
    {
        $response = $permissions === null ? $this->agent($tool, $arguments) : $this->agent($tool, $arguments, $permissions);
        $response->assertOk();
        $content = $this->content($response);
        $this->answered .= json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

        return $content;
    }

    /** @return array<string, mixed> */
    protected function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }

    /** @param  array<string, mixed>  $arguments */
    protected function refused(string $tool, array $arguments, string $words): void
    {
        $this->agent($tool, $arguments)->assertHasErrors([$words]);
    }
}
