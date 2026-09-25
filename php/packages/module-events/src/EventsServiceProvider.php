<?php

declare(strict_types=1);

namespace WebxUi\Events;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use WebxUi\Admin\Categories\CategoryLinkSource;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Events\Handlers\CategoryHandler;
use WebxUi\Events\Handlers\EventHandler;
use WebxUi\Events\Http\Controllers\CalendarController;
use WebxUi\Events\Http\Controllers\IndexController;
use WebxUi\Events\Links\EventLinkSource;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Events\Panel\CategoriesModule;
use WebxUi\Events\Panel\EventsGroup;
use WebxUi\Events\Panel\EventsModule;
use WebxUi\Events\Relations\EventTarget;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Routing\Formatters\Prefixed;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Sitemap\SitemapRoutes;

/**
 * Two entities with addresses, two routes that are not entities — the index and the calendar
 * file — and the screens they are edited on. What is shared is somebody else's: the categories and
 * the relations are `module-admin`'s, the addresses `routing`'s, the sitemap and the `<head>`
 * `module-seo`'s.
 */
class EventsServiceProvider extends ServiceProvider
{
    /** The name of the index route — what the sitemap and a site's templates know it by. */
    public const INDEX_ROUTE = 'webx.events.index';

    /** The name of the calendar file's route. */
    public const ICS_ROUTE = 'webx.events.ics';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-events.php', 'webx-events');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-events');

        // Before anything is registered under it: a prefix nobody gave is a site whose events
        // would stand among its pages, and that is refused rather than half built.
        $prefix = self::prefix($this->config());

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-events');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerRouteTypes($prefix);
        $this->registerPublicRoutes($prefix);
        $this->registerScreens();
        $this->registerLinkSources();
        $this->app->make(RelationTargets::class)->register(new EventTarget);
        $this->registerPanel();

        if ((bool) $this->config()->get('webx-events.index', true)) {
            $this->app->make(SitemapRoutes::class)->register(self::INDEX_ROUTE);
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-events.php' => config_path('webx-events.php'),
        ], 'webx-events-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-events'),
        ], 'webx-events-lang');

        // The page and its parts are the least markup that works, and meant to be rewritten one
        // part at a time: a site publishes them all and deletes what it keeps as it is.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-events'),
        ], 'webx-events-views');
    }

    /**
     * The prefix, normalised — and never empty (decision 12): flat addresses of events at the
     * root of the site would argue with the tree of pages over every address.
     *
     * @throws InvalidArgumentException
     */
    public static function prefix(Config $config): string
    {
        $prefix = UrlNormaliser::key((string) $config->get('webx-events.prefix', 'events'));

        if ($prefix === '') {
            throw new InvalidArgumentException((string) __('webx-events::errors.prefix'));
        }

        return $prefix;
    }

    /**
     * An event and a category on one level under one prefix — so an event in two categories has
     * one address. `Fail` on both: a clash is an error under the field, not a quiet `-2`.
     */
    private function registerRouteTypes(string $prefix): void
    {
        $types = $this->app->make(RouteTypes::class);
        $flat = new Prefixed($prefix, Slug::class);

        $types->register(new RouteType(
            type: Event::TYPE,
            model: Event::class,
            formatter: $flat,
            handler: EventHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));

        $types->register(new RouteType(
            type: EventCategory::TYPE,
            model: EventCategory::class,
            formatter: $flat,
            handler: CategoryHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));
    }

    /**
     * The index and the calendar files: ordinary routes, so they win before the registry's
     * fallback, and `Reserved` closes the index's address to pages of its own accord. The index
     * switched off is not registered, and the address is free for a page (decision 12); the
     * calendar files stay, since they belong to the events and not to the index.
     *
     * Twice where the language is in the path; the prefixed copy would match `anything/events`
     * without {@see OneSpellingPerAddress} in front of it.
     */
    private function registerPublicRoutes(string $prefix): void
    {
        /** @var list<string> $middleware */
        $middleware = array_values((array) $this->config()->get('webx-events.middleware', ['web', 'webx.locale']));
        $localised = (string) $this->config()->get('webx-localization.strategy', 'prefix') === 'prefix';
        $language = '{'.OneSpellingPerAddress::PARAMETER.'}/';

        Route::get($prefix.'/{path}.ics', CalendarController::class)
            ->where('path', '[^/]+')
            ->middleware($middleware)
            ->name(self::ICS_ROUTE);

        if ($localised) {
            Route::get($language.$prefix.'/{path}.ics', CalendarController::class)
                ->where('path', '[^/]+')
                ->middleware([...$middleware, OneSpellingPerAddress::class])
                ->name(self::ICS_ROUTE.'.localised');
        }

        if (! (bool) $this->config()->get('webx-events.index', true)) {
            return;
        }

        Route::get($prefix, IndexController::class)
            ->middleware($middleware)
            ->name(self::INDEX_ROUTE);

        if ($localised) {
            Route::get($language.$prefix, IndexController::class)
                ->middleware([...$middleware, OneSpellingPerAddress::class])
                ->name(self::INDEX_ROUTE.'.localised');
        }
    }

    /**
     * The editor of an event and the form of its category, both described, so a project adds a
     * field with a patch and `module-seo` its card the same way.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Event::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->register(EventCategory::SCREEN, __DIR__.'/../resources/screens/category-form.json');

        // `wx-categories` names its categories by the path they answer at; this is where that
        // path is told which table the ids live in. From the provider, which `route:cache` runs.
        $this->app->make(CategorySources::class)
            ->register('events/categories', EventCategory::class, 'webx-events::errors.unknown-category');
    }

    /** A menu names a format of events as often as a single event. */
    private function registerLinkSources(): void
    {
        $links = $this->app->make(LinkSources::class);

        $links->register($this->app->make(EventLinkSource::class));
        $links->register(new CategoryLinkSource(
            EventCategory::class,
            EventCategory::TYPE,
            static fn (): string => (string) __('webx-events::module.categories'),
            'folder',
            241,
        ));
    }

    /**
     * Two sections in a group of their own (§4.9): Events · Categories. The group is added to the
     * panel's config at boot, because a site that published `webx-admin.php` has its own copy of
     * the list (CLAUDE.md §4).
     */
    private function registerPanel(): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $this->config()->get('webx-admin.groups', []);

        if (! array_key_exists(EventsGroup::GROUP, $groups)) {
            $this->config()->set('webx-admin.groups', [
                ...$groups,
                EventsGroup::GROUP => [
                    'title' => 'webx-events::module.group',
                    'icon' => 'calendar',
                    'order' => 600,
                ],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach ([EventsModule::class, CategoriesModule::class] as $module) {
            $modules->register($this->app->make($module));
        }
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
