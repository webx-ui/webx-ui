<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use Illuminate\Support\ServiceProvider;
use WebxUi\Geography\Console\ImportCommand;
use WebxUi\Geography\Matching\CarrierBindings;
use WebxUi\Geography\Matching\ConfigLeadingCarriers;
use WebxUi\Geography\Matching\LeadingCarriers;
use WebxUi\Geography\Matching\NoCarrierBindings;

class GeographyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-geography.php', 'webx-geography');

        // One registry for the request: owners of points, bindings and orders add their checks
        // from their own providers, and the editor asks all of them before a delete.
        $this->app->singleton(LocalityReferences::class);
        $this->app->singleton(LocalitySearch::class);
        $this->app->singleton(Editor::class);
        // The delivery module rebinds it over the site settings (§4.4).
        $this->app->singletonIf(LeadingCarriers::class, ConfigLeadingCarriers::class);
        // The delivery module rebinds it over its bindings table (§4.3).
        $this->app->singletonIf(CarrierBindings::class, NoCarrierBindings::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([__DIR__.'/../config/webx-geography.php' => config_path('webx-geography.php')], 'webx-geography-config');

        if ($this->app->runningInConsole()) {
            $this->commands([ImportCommand::class]);
        }
    }
}
