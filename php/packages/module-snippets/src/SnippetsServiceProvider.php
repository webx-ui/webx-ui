<?php

declare(strict_types=1);

namespace WebxUi\Snippets;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Cache\CacheGroups;
use WebxUi\Localization\Locales;
use WebxUi\Snippets\Checks\PlacesCheck;
use WebxUi\Snippets\Doctor\SnippetPlaces;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Output\Codes;
use WebxUi\Snippets\Panel\SnippetsModule;
use WebxUi\Snippets\View\Components\Place;
use WebxUi\Themes\Contracts\ThemeCheckPart;

/**
 * Codes of outside services and the places they are printed in (the snippets spec).
 *
 * The site's half is one Blade component, `<x-webx-snippets place="…" />`, which theme-default's
 * layout prints three times. The panel's half is a section behind one dangerous permission, and
 * the checks that say when a place is lost: `webx:theme:check` and `webx:doctor`.
 */
class SnippetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-snippets.php', 'webx-snippets');

        $this->app->singleton(Codes::class);

        // Content: what an editor pasted moves with webx:snapshot. Outside production a copy
        // prints only what is marked for dev and stage, so a stand never counts real visits.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('snippets_codes');
        });

        $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
            $checks->register(SnippetPlaces::class);
        });

        // The places in `webx:theme:check`, when the themes engine is there to ask.
        if (interface_exists(ThemeCheckPart::class)) {
            $this->app->tag([PlacesCheck::class], ThemeCheckPart::TAG);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-snippets');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-snippets');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->registerCacheGroups();

        Blade::component('webx-snippets', Place::class);
        $this->registerScreens();
        $this->registerHistory();

        $this->app->make(ModuleRegistry::class)->register($this->app->make(SnippetsModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-snippets.php' => config_path('webx-snippets.php'),
        ], 'webx-snippets-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-snippets'),
        ], 'webx-snippets-lang');
    }

    /**
     * The editor of a snippet, with the places and the languages laid over it when it is built:
     * both come from the config and the site, never from the JSON.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Snippet::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->extend(Snippet::SCREEN, fn (): array => [
            ['op' => 'set', 'target' => 'place', 'props' => ['options' => $this->app->make(Places::class)->options()]],
            ['op' => 'set', 'target' => 'locales', 'props' => ['options' => array_map(
                static fn (string $code): array => ['value' => $code, 'label' => $code],
                $this->app->make(Locales::class)->codes(),
            )]],
        ]);
    }

    /** Who changed which snippet and how — the code before and after, whole (decision 4). */
    private function registerHistory(): void
    {
        $fields = [];

        foreach (['name', 'place', 'code', 'consent', 'only_paths', 'except_paths', 'locales', 'on_stage', 'enabled', 'position'] as $field) {
            $fields[$field] = 'webx-snippets::screen.'.str_replace('_', '-', $field);
        }

        $this->app->make(HistoryTypes::class)->register(
            Snippet::TYPE,
            Snippet::class,
            fields: $fields,
            permission: SnippetsModule::PERMISSION,
            module: SnippetsModule::ID,
            label: 'webx-snippets::module.snippet',
        );
    }

    /**
     * The `snippets` cache group (WEBX_UI_CACHE.md §2): a snippet saved moves it, and a move
     * forgets the codes kept on a key of their own — so that «reset the site cache» reaches them
     * until they live on a versioned key.
     */
    private function registerCacheGroups(): void
    {
        $groups = $this->app->make(CacheGroups::class);

        $groups->declare(Codes::GROUP, 'webx-snippets::module.title');
        $groups->bumpOn(Codes::GROUP, Snippet::class);
    }
}
