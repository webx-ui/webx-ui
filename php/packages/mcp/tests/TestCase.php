<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class, McpServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /**
     * The package's own tables, for every test: a call is written down whichever way it went,
     * so even a test about a refusal needs somewhere to write it.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function register(Module ...$modules): void
    {
        $registry = $this->app->make(ModuleRegistry::class);

        foreach ($modules as $module) {
            $registry->register($module);
        }
    }

    protected function tools(): ToolRegistry
    {
        return $this->app->make(ToolRegistry::class);
    }
}
