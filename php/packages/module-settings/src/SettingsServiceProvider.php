<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\Events\StoredContentRewritten;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Settings\Console\MoveContactsCommand;
use WebxUi\Settings\Contacts\Contacts;
use WebxUi\Settings\Events\SettingsSaved;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('cms_settings');
            // A key in `webx-settings.stand_own` keeps this stand's value through a restore: a key of
            // an integration that differs per stand, should one ever be stored here rather than in .env.
            $tables->preserve('cms_settings', 'key', static fn (): array => array_values(array_map(strval(...), (array) config('webx-settings.stand_own', []))));
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-settings.php', 'webx-settings');

        $this->app->singleton(Settings::class);
        // Scoped: it remembers what it read, for one request of a long-lived worker.
        $this->app->scoped(Contacts::class);

        // What the panel wears is a setting like any other, so the section that holds the
        // settings is the one that answers the frame's question about it.
        $this->app->singleton(BrandingSource::class, PanelBranding::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-settings');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->make(ModuleRegistry::class)->register($this->app->make(SettingsModule::class));

        // Values rewritten around the model (the library moving a logo to a new key): the cached
        // settings still hold the old one.
        $this->app->make(Dispatcher::class)->listen(StoredContentRewritten::class, function (): void {
            $this->app->make(Settings::class)->forget();
        });

        $this->app->make(Dispatcher::class)->listen(SettingsSaved::class, function (): void {
            if ($this->app->resolved(Contacts::class)) {
                $this->app->make(Contacts::class)->forget();
            }
        });

        // The reference screen. A project lays its own tabs over it from its provider, which
        // boots after this one — `Screens::extend('settings.index', ...)`.
        $this->app->make(ScreenRegistry::class)->register(Settings::SCREEN, __DIR__.'/../resources/screens/index.json');
        $this->app->make(ScreenRegistry::class)->register(Settings::CONTENT_SCREEN, __DIR__.'/../resources/screens/content.json');

        // The shortcodes defined in the panel, read when a page asks rather than at boot: they
        // change without a deploy.
        $this->app->make(Shortcodes::class)->source(fn (): array => $this->app->make(DataShortcodes::class)->all());

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([MoveContactsCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-settings.php' => config_path('webx-settings.php'),
        ], 'webx-settings-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-settings'),
        ], 'webx-settings-lang');
    }
}
