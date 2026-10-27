<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Localization\LocalizationServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // A test that reaches the network waits on it; whatever it calls is faked.
        Http::preventStrayRequests();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LocalizationServiceProvider::class, AdminServiceProvider::class];
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
