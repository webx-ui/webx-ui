<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\ModuleRegistry;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // The panel is served through the `web` group, which encrypts cookies and so refuses
        // to run without a key.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function registry(): ModuleRegistry
    {
        return $this->app->make(ModuleRegistry::class);
    }

    protected function register(Module ...$modules): void
    {
        foreach ($modules as $module) {
            $this->registry()->register($module);
        }
    }
}
