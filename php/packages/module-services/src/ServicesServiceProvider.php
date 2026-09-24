<?php

declare(strict_types=1);

namespace WebxUi\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Categories\CategoryLinkSource;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Routing\Formatters\Prefixed;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Sitemap\SitemapRoutes;
use WebxUi\Services\Handlers\CategoryHandler;
use WebxUi\Services\Handlers\ServiceHandler;
use WebxUi\Services\Http\Controllers\IndexController;
use WebxUi\Services\Links\ServiceLinkSource;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;
use WebxUi\Services\Panel\CategoriesModule;
use WebxUi\Services\Panel\ServicesGroup;
use WebxUi\Services\Panel\ServicesModule;

/**
 * Two entities with addresses, both made of blocks, one route that is not an entity, and the
 * screens they are edited on. Everything else is somebody else's: the categories are
 * `module-admin`'s, the addresses `routing`'s, the sitemap and the `<head>` `module-seo`'s.
 */
class ServicesServiceProvider extends ServiceProvider
{
    /** The name of the index route — what the sitemap and a site's templates know it by. */
    public const INDEX_ROUTE = 'webx.services.index';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-services.php', 'webx-services');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-services');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-services');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerRouteTypes();
        $this->registerIndexRoute();
        $this->registerBlockEntities();
        $this->registerScreens();
        $this->registerLinkSources();
        $this->registerPanel();

        $this->app->make(SitemapRoutes::class)->register(self::INDEX_ROUTE);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-services.php' => config_path('webx-services.php'),
        ], 'webx-services-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-services'),
        ], 'webx-services-lang');

        // The three public views are the least markup that works, and meant to be published and
        // rewritten: a catalogue is the part of a site that looks like the site.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-services'),
        ], 'webx-services-views');
    }

    /**
     * A service and a category on one level under one prefix (§4.3) — so a service in three
     * categories has one address, and there is no question of which of three is canonical.
     *
     * `Fail` on both: a category and a service that want the same slug are an error under the
     * field, not a quiet `-2` somebody finds months later in a search result.
     */
    private function registerRouteTypes(): void
    {
        $types = $this->app->make(RouteTypes::class);
        $prefix = $this->prefix();

        // Read once, here: a formatter has to be a pure function of the entity, so that the
        // observer and `webx:routes:rebuild` cannot disagree about where a service lives.
        $flat = $prefix === '' ? new Slug : new Prefixed($prefix, Slug::class);

        $types->register(new RouteType(
            type: 'service',
            model: Service::class,
            formatter: $flat,
            handler: ServiceHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));

        $types->register(new RouteType(
            type: 'service-category',
            model: ServiceCategory::class,
            formatter: $flat,
            handler: CategoryHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));
    }

    /**
     * The index: an ordinary route, so it wins before the registry's fallback and `Reserved`
     * closes the address to pages of its own accord. With no prefix it is not registered at all —
     * `/` is the site's, and a list of services there is a page the site writes. Switched off, it
     * is not registered either, and for the same reason: the address is then free for a page.
     *
     * Twice where the language is in the path; the prefixed copy would match `anything/services`
     * without {@see OneSpellingPerAddress} in front of it.
     */
    private function registerIndexRoute(): void
    {
        $prefix = $this->prefix();

        if ($prefix === '' || ! (bool) $this->config()->get('webx-services.index', true)) {
            return;
        }

        /** @var list<string> $middleware */
        $middleware = array_values((array) $this->config()->get('webx-services.middleware', ['web', 'webx.locale']));

        Route::get($prefix, IndexController::class)
            ->middleware($middleware)
            ->name(self::INDEX_ROUTE);

        if ((string) $this->config()->get('webx-localization.strategy', 'prefix') !== 'prefix') {
            return;
        }

        Route::get('{'.OneSpellingPerAddress::PARAMETER.'}/'.$prefix, IndexController::class)
            ->middleware([...$middleware, OneSpellingPerAddress::class])
            ->name(self::INDEX_ROUTE.'.localised');
    }

    /**
     * Both are made of blocks, which `webx:blocks:bundles --warm` has to be told: the config of
     * `module-blocks` cannot know them.
     */
    private function registerBlockEntities(): void
    {
        /** @var list<string> $entities */
        $entities = (array) $this->config()->get('webx-blocks.entities', []);

        foreach ([Service::class, ServiceCategory::class] as $model) {
            if (! in_array($model, $entities, true)) {
                $entities[] = $model;
            }
        }

        $this->config()->set('webx-blocks.entities', $entities);
    }

    /**
     * The editor of a service and the page of a category, both described, so a project adds a
     * field with a patch and `module-seo` its card the same way.
     *
     * The Blocks tab of a category is a patch of this module's own, applied only when the site
     * said its category pages print blocks (§4.2): a tab whose content the page never shows is
     * a trap for the editor.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Service::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->register(ServiceCategory::SCREEN, __DIR__.'/../resources/screens/category-form.json');

        if ((bool) $this->config()->get('webx-services.categories.blocks', false)) {
            $screens->extend(ServiceCategory::SCREEN, __DIR__.'/../resources/screens/category-blocks.json');
        }

        // `wx-categories` on the service form names its categories by the path they answer at;
        // this is where that path is told which table the ids live in. From the provider rather
        // than the routes file, which `route:cache` never runs.
        $this->app->make(CategorySources::class)->register(
            'services/categories',
            ServiceCategory::class,
            'webx-services::errors.unknown-category',
        );
    }

    /**
     * A menu names a section of the catalogue as often as a single service, so both are
     * something to link to.
     */
    private function registerLinkSources(): void
    {
        $links = $this->app->make(LinkSources::class);

        $links->register($this->app->make(ServiceLinkSource::class));
        $links->register(new CategoryLinkSource(
            ServiceCategory::class,
            'service-category',
            static fn (): string => (string) __('webx-services::module.categories'),
            'folder',
            221,
        ));
    }

    /**
     * Two sections in a group of their own (§4.6): the services, and their categories.
     *
     * The group is added to the panel's config at boot rather than shipped as a default, because a
     * site that published `webx-admin.php` has its own copy of the list (CLAUDE.md §4).
     */
    private function registerPanel(): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $this->config()->get('webx-admin.groups', []);

        if (! array_key_exists(ServicesGroup::GROUP, $groups)) {
            $this->config()->set('webx-admin.groups', [
                ...$groups,
                ServicesGroup::GROUP => [
                    'title' => 'webx-services::module.group',
                    'icon' => 'briefcase',
                    'order' => 400,
                ],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach ([ServicesModule::class, CategoriesModule::class] as $module) {
            $modules->register($this->app->make($module));
        }
    }

    private function prefix(): string
    {
        return UrlNormaliser::key((string) $this->config()->get('webx-services.prefix', 'services'));
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
