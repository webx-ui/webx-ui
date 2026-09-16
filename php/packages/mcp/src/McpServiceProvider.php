<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\McpServiceProvider as LaravelMcpServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Mcp\Console\IssueTokenCommand;
use WebxUi\Mcp\Console\ListToolsCommand;
use WebxUi\Mcp\Http\Middleware\AuthenticateAgent;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\WebxServer;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-mcp.php', 'webx-mcp');

        // Registered here rather than left to package discovery, so that a Testbench or an
        // application that lists providers by hand gets the transport with the contract.
        $this->app->register(LaravelMcpServiceProvider::class);

        $this->app->singleton(
            ToolRegistry::class,
            static fn ($app): ToolRegistry => new ToolRegistry($app->make(ModuleRegistry::class)),
        );
    }

    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('webx.mcp-auth', AuthenticateAgent::class);

        $this->registerServers();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([ListToolsCommand::class, IssueTokenCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-mcp.php' => config_path('webx-mcp.php'),
        ], 'webx-mcp-config');
    }

    /**
     * The one server, over HTTP next to the panel's API and over stdio by name. The routes
     * are skipped when the router's cache is in use, the way `loadRoutesFrom()` skips: they
     * are in the cache already.
     */
    private function registerServers(): void
    {
        $config = $this->app->make('config');
        $path = $config->get('webx-mcp.path');

        if ($path !== false && ! $this->routesAreCached()) {
            $path = is_string($path) && $path !== ''
                ? $path
                : trim((string) $config->get('webx-admin.api_path', 'api/cms'), '/').'/mcp';

            $middleware = $config->get('webx-mcp.middleware', []);

            Mcp::web($path, WebxServer::class)
                ->middleware(is_array($middleware) ? $middleware : [$middleware])
                ->name('webx.mcp');
        }

        $local = $config->get('webx-mcp.local');

        if (is_string($local) && $local !== '') {
            Mcp::local($local, WebxServer::class);
        }
    }

    private function routesAreCached(): bool
    {
        return $this->app instanceof CachesRoutes && $this->app->routesAreCached();
    }
}
