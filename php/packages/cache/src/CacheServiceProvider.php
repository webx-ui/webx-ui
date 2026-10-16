<?php

declare(strict_types=1);

namespace WebxUi\Cache;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;

/**
 * One registry of cache groups for the whole application (WEBX_UI_CACHE.md): the packages
 * declare their groups into it from their own providers, and whoever caches reads the versions
 * from it — once a request.
 *
 * A package that declares a group registers this provider itself (`$this->app->register()`),
 * so that the registry is a singleton before any `boot()` asks for it, whether the site found
 * the package by discovery or a test listed the providers by hand.
 */
class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-cache.php', 'webx-cache');

        $this->app->singleton(CacheGroups::class);
    }

    public function boot(): void
    {
        // A worker that serves many requests in one process must not keep the first one's versions.
        if (class_exists(RequestReceived::class)) {
            $this->app->make(Dispatcher::class)->listen(RequestReceived::class, function (): void {
                $this->app->make(CacheGroups::class)->forgetMemo();
            });
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-cache.php' => config_path('webx-cache.php'),
        ], 'webx-cache-config');
    }
}
