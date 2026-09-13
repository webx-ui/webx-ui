<?php

declare(strict_types=1);

namespace WebxUi\Admin;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Console\InstallCommand;
use WebxUi\Admin\Console\MakeModuleCommand;
use WebxUi\Admin\Console\PanelCommand;
use WebxUi\Admin\Manifest\ManifestBuilder;
use WebxUi\Localization\Locales;

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
                $app->make(Locales::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-admin');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-admin');
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

        // Publishing these is how a site adds a language the package never shipped, or
        // disagrees with a word in one it did.
        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-admin'),
        ], 'webx-admin-lang');

        $this->commands([
            InstallCommand::class,
            MakeModuleCommand::class,
            PanelCommand::class,
        ]);
    }
}
