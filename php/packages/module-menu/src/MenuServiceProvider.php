<?php

declare(strict_types=1);

namespace WebxUi\Menu;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Contracts\SiteUrls;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Menu\Panel\MenuModule;
use WebxUi\Menu\Rendering\Builder;

/**
 * The menus of the site.
 *
 * Two halves that barely touch: the tables and the render, which is all of this session, and the
 * panel on top of them. What is worth noticing here is when the cache subscribes — after the
 * application has booted, because the list of things a menu can point at is filled by other
 * modules from their own `boot()`, and a subscription taken before them would listen to nothing.
 */
class MenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-menu.php', 'webx-menu');

        $this->app->singleton(MenuCache::class);
        $this->app->singleton(Menus::class);
        $this->app->singleton(CacheSubscriber::class);

        // Resolved rather than injected, so that the check happens when a menu is first built
        // rather than while providers are still registering.
        $this->app->bind(Builder::class, static fn ($app): Builder => new Builder(
            $app->make(LinkSources::class),
            $app->bound(SiteUrls::class) ? $app->make(SiteUrls::class) : null,
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-menu');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-menu');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        Blade::componentNamespace('WebxUi\\Menu\\View\\Components', 'webx-menu');

        $this->app->make(ModuleRegistry::class)->register($this->app->make(MenuModule::class));

        $this->app->booted(function (): void {
            $this->app->make(CacheSubscriber::class)->subscribe();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-menu.php' => config_path('webx-menu.php'),
        ], 'webx-menu-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-menu'),
        ], 'webx-menu-lang');

        // The markup is meant to be published and rewritten on the second day. It exists so
        // that the first day of a new site does not begin with writing a `<ul>`.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-menu'),
        ], 'webx-menu-views');
    }
}
