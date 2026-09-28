<?php

declare(strict_types=1);

namespace WebxUi\Menu;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Contracts\SiteUrls;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Menu\Models\Menu;
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

        $this->offerBlock();

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

    /**
     * A block type that prints a menu (layout regions §5.2), offered to `module-blocks` when the
     * site has it: the header of a site built from blocks is the first place a menu stands, and
     * `@foreach (menu('header') …)` with `attrs()` and `isActive()` is what every site would
     * otherwise write again. Offered, not installed — `webx:blocks:offered --install` puts it on
     * the site once, and a type the site already has by that name is never touched.
     *
     * `module-blocks` is not a dependency of this package, so the class is asked for first and
     * the binding second: a checkout that has the class but not the provider has nobody to
     * offer to.
     */
    private function offerBlock(): void
    {
        if (! class_exists(BlockOffers::class) || ! $this->app->bound(BlockOffers::class)) {
            return;
        }

        $this->app->make(BlockOffers::class)->offer(MenuModule::ID, __DIR__.'/../resources/blocks', self::withMenus(...));
    }

    /**
     * The document with the menus of the site as the choices of its `menu` field — declared
     * ones first, then those made in the panel. Read when the type is installed, which is
     * the moment the site's config and its menus are known; a menu made later is added to
     * the list in the block type's form.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function withMenus(array $document): array
    {
        $menus = app(Menus::class);
        $titles = Menu::query()->get()->mapWithKeys(
            static fn (Menu $menu): array => [$menu->key => $menu->label('en')],
        )->all();

        $options = array_map(
            static fn (string $key): array => ['value' => $key, 'label' => $titles[$key] ?? $menus->declaredTitle($key) ?? $key],
            $menus->keys(),
        );

        $schema = is_array($document['schema'] ?? null) ? $document['schema'] : [];

        foreach ($schema as $i => $field) {
            if (is_array($field) && ($field['id'] ?? null) === 'menu') {
                $schema[$i]['props'] = [
                    ...(is_array($field['props'] ?? null) ? $field['props'] : []),
                    'options' => $options,
                ];
            }
        }

        $document['schema'] = $schema;

        return $document;
    }
}
