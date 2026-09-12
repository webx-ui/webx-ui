<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Mcp\Console\ListToolsCommand;
use WebxUi\Mcp\Registry\ToolRegistry;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ToolRegistry::class,
            static fn ($app): ToolRegistry => new ToolRegistry($app->make(ModuleRegistry::class)),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ListToolsCommand::class]);
        }
    }
}
