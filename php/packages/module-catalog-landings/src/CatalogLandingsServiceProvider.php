<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Catalog\Events\FacetValueRetargeted;
use WebxUi\Catalog\Events\ProductsIndexed;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Catalog\Mcp\SatelliteTools;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogLandings\Catalog\IndexedBases;
use WebxUi\CatalogLandings\Catalog\LandingRepairs;
use WebxUi\CatalogLandings\Console\CountCommand;
use WebxUi\CatalogLandings\Mcp\LandingsResource;
use WebxUi\CatalogLandings\Mcp\LandingTools;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogLandings\Panel\LandingsModule;
use WebxUi\CatalogLandings\Storefront\LandingHandler;
use WebxUi\CatalogLandings\Storefront\LandingPart;
use WebxUi\CatalogLandings\Storefront\LandingRewriter;
use WebxUi\CatalogLandings\Storefront\LandingViews;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;

/**
 * Landings of the catalogue (`WEBX_UI_CATALOG_LANDINGS.md`): a satellite that knows only the
 * core. What it registers: a type of address beside the categories', the rewriter that gives sets
 * those addresses, the strip and the links in the storefront's points, the listeners that keep a
 * set true to its values and a count true to the list, the API and the journal.
 *
 * The section of the panel is «Catalog» → «Landings» with the form `catalog.landing-form`; the
 * agent's tools come with the next stage (§13).
 */
class CatalogLandingsServiceProvider extends ServiceProvider
{
    /** Where the landings answer under the panel's API. */
    public const SOURCE = 'catalog/landings';

    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('catalog_landings', 'catalog_landing_products');
            $tables->stand('catalog_landing_runs');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog-landings.php', 'webx-catalog-landings');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-landings');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-catalog-landings');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerAddresses();
        $this->registerStorefront();
        $this->registerListeners();
        $this->registerHistory();

        $this->app->make(ScreenRegistry::class)->register(Landing::SCREEN, __DIR__.'/../resources/screens/landing-form.json');
        $this->app->make(ModuleRegistry::class)->register($this->app->make(LandingsModule::class));

        // The agent's tools and `catalog://landings` are the catalogue's (§11): its names, scopes and
        // permissions. Built when an agent asks, not at boot.
        $mcp = $this->app->make(SatelliteTools::class);
        $mcp->tools(fn (): array => $this->app->make(LandingTools::class)->all());
        $mcp->resources(fn (): array => [$this->app->make(LandingsResource::class)->resource()]);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([CountCommand::class]);
        $this->registerSchedule();

        $this->publishes([
            __DIR__.'/../config/webx-catalog-landings.php' => config_path('webx-catalog-landings.php'),
        ], 'webx-catalog-landings-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-landings'),
        ], 'webx-catalog-landings-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-catalog-landings'),
        ], 'webx-catalog-landings-views');
    }

    /**
     * `/{slug}/` from the site's root, in the categories' space (§6.1): a slug a category holds
     * cannot be a landing's, and the other way round — a save fails under the field with the
     * holder's name. The rewriter gives the sets their addresses in every link of the filter.
     */
    private function registerAddresses(): void
    {
        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: Landing::TYPE,
            model: Landing::class,
            formatter: Slug::class,
            handler: LandingHandler::class,
            acceptsTail: true,
            onConflict: OnConflict::Fail,
        ));

        $this->app->make(FilterUrls::class)->register($this->app->make(LandingRewriter::class));
    }

    /**
     * Over the list, the recommended strip and the collections; under it, the neighbours; beside a
     * product, the landings that hold it (§6.2, §7). The data comes from the views' composers, so
     * a site that publishes a view keeps it.
     */
    private function registerStorefront(): void
    {
        $parts = $this->app->make(StorefrontParts::class);
        $parts->register(new LandingPart('catalog.listing.top', 'webx-catalog-landings::parts.listing-top'));
        $parts->register(new LandingPart('catalog.listing.bottom', 'webx-catalog-landings::parts.listing-bottom'));
        $parts->register(new LandingPart('catalog.product.aside', 'webx-catalog-landings::parts.product'));

        View::composer('webx-catalog-landings::parts.listing-top', fn ($view) => $this->app->make(LandingViews::class)->top($view));
        View::composer('webx-catalog-landings::parts.listing-bottom', fn ($view) => $this->app->make(LandingViews::class)->bottom($view));
        View::composer('webx-catalog-landings::parts.product', fn ($view) => $this->app->make(LandingViews::class)->product($view));
    }

    /**
     * A value merged or deleted reaches the sets that hold it (§9); a batch of the index marks the
     * landings of its categories for a count (§6.5).
     */
    private function registerListeners(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app->make('events');

        $events->listen(FacetValueRetargeted::class, [LandingRepairs::class, 'handle']);
        $events->listen(ProductsIndexed::class, [IndexedBases::class, 'handle']);
    }

    /**
     * What the journal shows for a landing: "Set", not `filters`. Read behind any of the
     * catalogue's permissions.
     */
    private function registerHistory(): void
    {
        $fields = [];

        foreach (Landing::HISTORY as $field) {
            $fields[$field] = 'webx-catalog-landings::landing.'.$field;
        }

        $this->app->make(HistoryTypes::class)->register(
            Landing::TYPE,
            Landing::class,
            fields: $fields,
            permission: ['catalog.view', 'catalog.manage', 'catalog.delete'],
            module: 'catalog-landings',
            label: 'webx-catalog-landings::module.landing',
        );
    }

    /**
     * The counts the index marked, every quarter of an hour; every count, nightly — the safety net
     * (§6.5). `onOneServer`, as the core's own.
     */
    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(CountCommand::class)->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command(CountCommand::class, ['--all'])->dailyAt('03:45')->onOneServer();
        });
    }
}
