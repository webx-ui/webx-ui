<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\Events\StoredContentRewritten;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Cache\CacheGroups;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Settings\Console\MoveContactsCommand;
use WebxUi\Settings\Contacts\Contacts;
use WebxUi\Settings\Events\SettingsSaved;
use WebxUi\Settings\Models\Setting;
use WebxUi\Settings\Sections\SettingsSections;
use WebxUi\Settings\Sections\SettingsSectionsCheck;

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
        $this->registerCacheGroups();
        $this->app->make(DoctorChecks::class)->register(SettingsSectionsCheck::class);

        // Values rewritten around the model (the library moving a logo to a new key): the cached
        // settings still hold the old one.
        $this->app->make(Dispatcher::class)->listen(StoredContentRewritten::class, function (): void {
            $this->app->make(Settings::class)->forget();
        });

        // The logo is printed by the header on every page, after the content's blocks looked their
        // pictures up: the first look-up of a response takes the settings' pictures along, so the
        // page asks the library once rather than once more for the logo.
        if (class_exists(MediaFiles::class) && $this->app->bound(MediaFiles::class)) {
            $this->app->make(MediaFiles::class)->prefetch(fn () => $this->app->make(Settings::class)->preload());
        }

        $this->app->make(Dispatcher::class)->listen(SettingsSaved::class, function (): void {
            if ($this->app->resolved(Contacts::class)) {
                $this->app->make(Contacts::class)->forget();
            }
        });

        // The reference screen. A project lays its own tabs over it from its provider, which
        // boots after this one — `Screens::extend('settings.index', ...)`.
        $this->app->make(ScreenRegistry::class)->register(Settings::SCREEN, __DIR__.'/../resources/screens/index.json');
        // The tabs nobody on this site uses, or that cannot be drawn here, and the Contacts sections
        // nobody prints are hidden by a patch of the section's own, laid before the project's: the
        // nodes stay in the tree, so a project patch that targets one still applies. Worked out when
        // the tree is built — a field type is registered by a provider that may boot after this one.
        $this->app->make(ScreenRegistry::class)->extend(Settings::SCREEN, fn (): array => $this->app->make(SettingsSections::class)->patch());
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

    /**
     * The `settings` cache group (WEBX_UI_CACHE.md §2): every save of a setting moves it, and
     * the table itself lives on its versioned key ({@see Settings::raw()}) — a seeder, a
     * removal, a console one-liner all go through the model, and a row written round it says
     * `Settings::forget()`.
     */
    private function registerCacheGroups(): void
    {
        $groups = $this->app->make(CacheGroups::class);

        $groups->declare(Settings::GROUP, 'webx-settings::module.title');
        $groups->bumpOn(Settings::GROUP, Setting::class);
        // Every page reads the settings: with the versions, in the same call.
        $groups->prefetch('settings', [Settings::GROUP], localized: false);
    }
}
