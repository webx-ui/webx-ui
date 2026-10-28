<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The cart on a site with the panel and the MCP server: what its agent tools are tested on. The
 * rest of the tests stand on the cart alone, which is how it must also work.
 */
abstract class McpTestCase extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            CartServiceProvider::class,
        ];
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function reader(array $permissions = ['cart.view']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Reader',
            'email' => "reader-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "reader-{$count}", 'name' => 'Reader', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->reader(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    protected function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertOk()->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
