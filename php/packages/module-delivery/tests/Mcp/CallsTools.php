<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Mcp;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/** A tool of the section called as an agent calls it: through the server, as an administrator. */
trait CallsTools
{
    protected ?CmsUser $agentUser = null;

    /** @param  array<string, mixed>  $arguments */
    protected function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
        $this->agentUser ??= $this->signIn(['delivery.view', 'delivery.manage', 'delivery.ship']);

        return WebxServer::actingAs($as ?? $this->agentUser, 'cms')->tool($bound, $arguments);
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

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function use(string $tool, array $arguments = []): array
    {
        $response = $this->agent($tool, $arguments);
        $response->assertOk();

        return $this->content($response);
    }
}
