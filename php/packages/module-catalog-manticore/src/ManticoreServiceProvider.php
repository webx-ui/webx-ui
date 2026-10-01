<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Catalog\Engine\CatalogEngines;
use WebxUi\Catalog\Manticore\Doctor\ManticoreCheck;

/**
 * Manticore for the catalogue (the Manticore spec): the engine under the name `manticore` among
 * the core's engines, and the doctor's check. A site switches with `WEBX_CATALOG_ENGINE=manticore`
 * and a `MANTICORE_TABLE_PREFIX` of its own, then fills the index once with
 * `php artisan webx:catalog:index --rebuild`; from there the core's queue keeps it current.
 */
class ManticoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog-manticore.php', 'webx-catalog-manticore');
    }

    public function boot(): void
    {
        $this->app->make(CatalogEngines::class)->register('manticore', ManticoreEngine::class);
        $this->app->make(DoctorChecks::class)->register(ManticoreCheck::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webx-catalog-manticore.php' => config_path('webx-catalog-manticore.php'),
            ], 'webx-catalog-manticore-config');
        }
    }
}
