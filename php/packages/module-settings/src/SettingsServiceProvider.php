<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-settings.php', 'webx-settings');

        $this->app->singleton(Settings::class);

        // What the panel wears is a setting like any other, so the section that holds the
        // settings is the one that answers the frame's question about it.
        $this->app->singleton(BrandingSource::class, PanelBranding::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-settings');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->make(ModuleRegistry::class)->register(new SettingsModule);

        // The reference screen. A project lays its own tabs over it from its provider, which
        // boots after this one — `Screens::extend('settings.index', ...)`.
        $this->app->make(ScreenRegistry::class)->register(Settings::SCREEN, __DIR__.'/../resources/screens/index.json');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-settings.php' => config_path('webx-settings.php'),
        ], 'webx-settings-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-settings'),
        ], 'webx-settings-lang');
    }
}
