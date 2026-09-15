<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\ServiceProvider;
use WebxUi\Routing\Aliases\DatabaseAliases;
use WebxUi\Routing\Aliases\RouteAliases;
use WebxUi\Routing\Console\CheckRoutesCommand;
use WebxUi\Routing\Console\RebuildRoutesCommand;
use WebxUi\Routing\Http\Controllers\ResolveController;

/**
 * The registry is two halves that meet only in `RouteTypes`: writing (the trait, the observer,
 * `RouteSync`) and reading (the resolver behind the fallback route). Which is why that singleton
 * is the one thing that has to exist before anything boots.
 */
class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-routing.php', 'webx-routing');

        $this->app->singleton(RouteTypes::class);
        $this->app->singleton(Reserved::class);
        $this->app->singleton(UniquePath::class);
        $this->app->singleton(RouteSync::class);
        $this->app->singleton(Resolver::class);

        // The one thing the registry offers a panel to read. Bound to an interface rather than
        // exposed as a class, because the screen that shows it lives in another package and a
        // table is not a contract.
        $this->app->singleton(RouteAliases::class, DatabaseAliases::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerFallback();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([RebuildRoutesCommand::class, CheckRoutesCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-routing.php' => config_path('webx-routing.php'),
        ], 'webx-routing-config');
    }

    /**
     * A fallback rather than a catch-all, and that is the whole trick: a fallback is tried only
     * when nothing else matched, so a project's own `/search` wins with no ordering to arrange
     * and nothing of theirs shadowed by ours.
     *
     * Registered in `boot()` so that it is there when routes are cached, and skipped entirely
     * when a project would rather call the resolver from a route of its own. Our provider boots
     * before the application's, so their fallback replaces ours rather than racing it.
     */
    private function registerFallback(): void
    {
        $config = $this->app->make('config');

        if (! (bool) $config->get('webx-routing.fallback', true)) {
            return;
        }

        /** @var list<string> $middleware */
        $middleware = (array) $config->get('webx-routing.middleware', ['web']);

        Router::fallback(ResolveController::class)
            ->middleware($middleware)
            ->name('webx.routing.resolve');
    }
}
