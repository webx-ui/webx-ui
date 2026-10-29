<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use Illuminate\Foundation\Application;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Orders\Tests\Panel\PanelTestCase;

/**
 * «Orders» with the MCP server beside the panel: what the `orders_*` tools are tested on — and
 * held to the panel's own answers.
 */
abstract class McpTestCase extends PanelTestCase
{
    /** Who the agent acts as unless a test says otherwise: every permission of the section. */
    private ?CmsUser $caller = null;

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

        return WebxServer::actingAs($as ?? $this->caller(), 'cms')->tool($bound, $arguments);
    }

    protected function caller(): CmsUser
    {
        return $this->caller ??= $this->manager(['orders.view', 'orders.manage', 'orders.refund', 'orders.statuses']);
    }

    /**
     * What the tool answered, as an array.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function ask(string $tool, array $arguments = [], ?CmsUser $as = null): array
    {
        $decoded = null;

        $this->agent($tool, $arguments, $as)->assertOk()->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
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
