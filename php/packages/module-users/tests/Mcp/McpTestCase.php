<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests\Mcp;

use Illuminate\Foundation\Application;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Users\Tests\Panel\PanelTestCase;

/**
 * «Customers» with the MCP server beside the panel: what the `users_*` tools are tested on.
 */
abstract class McpTestCase extends PanelTestCase
{
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
     */
    protected function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->administrator(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    protected function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }

    protected function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource [{$uri}].");
    }
}
