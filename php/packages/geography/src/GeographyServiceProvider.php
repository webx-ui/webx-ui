<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use Illuminate\Support\ServiceProvider;
use WebxUi\Geography\Console\ImportCommand;

class GeographyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One registry for the request: owners of points, bindings and orders add their checks
        // from their own providers, and the editor asks all of them before a delete.
        $this->app->singleton(LocalityReferences::class);
        $this->app->singleton(LocalitySearch::class);
        $this->app->singleton(Editor::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ImportCommand::class]);
        }
    }
}
