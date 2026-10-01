<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Catalog\Engine\CatalogEngines;
use WebxUi\Catalog\Manticore\Doctor\ManticoreCheck;
use WebxUi\Catalog\Manticore\Mcp\IndexTools;
use WebxUi\Catalog\Manticore\Panel\SearchIndexModule;
use WebxUi\Catalog\Mcp\SatelliteTools;

/**
 * Manticore for the catalogue (the Manticore spec): the engine under the name `manticore` among
 * the core's engines, and the doctor's check. A site switches with `WEBX_CATALOG_ENGINE=manticore`
 * and a `MANTICORE_TABLE_PREFIX` of its own, then fills the index once with
 * `php artisan webx:catalog:index --rebuild`; from there the core's queue keeps it current.
 *
 * On that engine the panel gets «System → Search index» and an agent `catalog_index_status`
 * (decisions 27–28); on the database engine neither is there.
 */
class ManticoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog-manticore.php', 'webx-catalog-manticore');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-manticore');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-catalog-manticore');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->make(CatalogEngines::class)->register('manticore', ManticoreEngine::class);
        $this->app->make(DoctorChecks::class)->register(ManticoreCheck::class);

        $module = $this->app->make(SearchIndexModule::class);

        if ($module->available()) {
            $this->app->make(ModuleRegistry::class)->register($module);
        }

        $this->app->make(SatelliteTools::class)->tools(fn (): array => $this->app->make(IndexTools::class)->all());

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webx-catalog-manticore.php' => config_path('webx-catalog-manticore.php'),
            ], 'webx-catalog-manticore-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/webx-catalog-manticore'),
            ], 'webx-catalog-manticore-views');

            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/webx-catalog-manticore'),
            ], 'webx-catalog-manticore-lang');
        }
    }
}
