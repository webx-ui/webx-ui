<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Support\ServiceProvider;

/**
 * The registry is two halves that never meet: writing (the trait, the observer, `RouteSync`)
 * and reading (the resolver, which arrives next). Both stand on `RouteTypes`, which is why it
 * is the only singleton that has to exist before anything boots.
 */
class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-routing.php', 'webx-routing');

        $this->app->singleton(RouteTypes::class);
        $this->app->singleton(UniquePath::class);
        $this->app->singleton(RouteSync::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-routing.php' => config_path('webx-routing.php'),
        ], 'webx-routing-config');
    }
}
