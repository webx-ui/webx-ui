<?php

declare(strict_types=1);

namespace WebxUi\Admin;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Console\InstallCommand;
use WebxUi\Admin\Console\MakeModuleCommand;
use WebxUi\Admin\Manifest\ManifestBuilder;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-admin.php', 'webx-admin');

        // One registry for the request: modules register into it from their own providers,
        // and the manifest reads whatever is there by the time a request arrives.
        $this->app->singleton(ModuleRegistry::class);

        $this->app->bind(
            ManifestBuilder::class,
            static fn ($app): ManifestBuilder => new ManifestBuilder(
                $app->make(ModuleRegistry::class),
                $app->make('config'),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-admin');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-admin.php' => config_path('webx-admin.php'),
        ], 'webx-admin-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-admin'),
        ], 'webx-admin-views');

        $this->commands([
            InstallCommand::class,
            MakeModuleCommand::class,
        ]);
    }
}
