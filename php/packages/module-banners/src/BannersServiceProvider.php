<?php

declare(strict_types=1);

namespace WebxUi\Banners;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Cache\CacheGroups;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;
use WebxUi\Banners\Panel\BannersModule;
use WebxUi\Banners\Rendering\Stylesheet;
use WebxUi\Banners\Screens\LayersType;
use WebxUi\Banners\View\Components\Banner as BannerComponent;
use WebxUi\Banners\View\Components\Banners as BannersComponent;

/**
 * The banners, their places and the screen they are edited on — and not one public route, not a
 * block (decision 7). A banner reaches the site through `banners('hero')` in a template of the
 * site, which prints its own markup — or through `<x-webx-banners>`, which prints the markup a
 * banner with layers needs (decision 10 of the layers spec).
 */
class BannersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('banners', 'banner_places');
        });

        $this->app->scoped(Stylesheet::class);

        $this->mergeConfigFrom(__DIR__.'/../config/webx-banners.php', 'webx-banners');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-banners');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-banners');
        $this->registerCacheGroups();

        Blade::component('webx-banners', BannersComponent::class);
        Blade::component('webx-banner', BannerComponent::class);

        $this->registerScreens();

        $this->app->make(ModuleRegistry::class)->register($this->app->make(BannersModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-banners.php' => config_path('webx-banners.php'),
        ], 'webx-banners-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-banners'),
        ], 'webx-banners-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-banners'),
        ], 'webx-banners-views');
    }

    /**
     * The editor of a banner, described, so a project adds a field with a patch — and the button
     * variants laid over it from the config (§5.3). The select has no options in the JSON: this
     * patch is where they come from, so a site adds a variant with one line of config, and the
     * check of the value is the one every `wx-select` already has on the server.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Banner::SCREEN, __DIR__.'/../resources/screens/form.json');
        $this->app->make(FieldTypes::class)->register('wx-banner-layers', new LayersType);
        $screens->extend(Banner::SCREEN, [[
            'op' => 'set',
            'target' => 'button-variant',
            'props' => ['options' => $this->app->make(Variants::class)->options()],
        ]]);
    }

    /**
     * The `banners` cache group (WEBX_UI_CACHE.md §2): a banner or a place saved moves it. Nothing
     * of the banners is cached yet; the group is where the places' queries go when they are.
     */
    private function registerCacheGroups(): void
    {
        $groups = $this->app->make(CacheGroups::class);

        $groups->declare('banners', 'webx-banners::module.banners');
        $groups->bumpOn('banners', Banner::class, Place::class);
    }
}
