<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\CatalogFeeds\Console\FeedsCommand;
use WebxUi\CatalogFeeds\Console\FeedsPruneCommand;
use WebxUi\CatalogFeeds\Doctor\FeedsCheck;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Panel\CatalogFeedsModule;

/**
 * Product feeds of the catalogue (`WEBX_UI_CATALOG_FEEDS.md`): a satellite that knows only the
 * core. A feed has no address in the routes' registry — it is a file behind a key, not a page —
 * so there is no `RouteType` and nothing for `SiteBar::entity()`.
 */
class CatalogFeedsServiceProvider extends ServiceProvider
{
    /** Where the feeds answer under the panel's API. */
    public const SOURCE = 'catalog/feeds';

    /** Its own, by key and address: a bare `throttle` would count a robot with the sign-in form. */
    public const LIMITER = 'webx-catalog-feeds';

    public function register(): void
    {
        // A feed's settings are content; its builds are what happened on this stand, and their
        // files stay here, so a restored feed answers 404 until it is built again.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('catalog_feeds', 'catalog_feed_exclusions', 'catalog_feed_categories', 'catalog_feed_taxonomies', 'catalog_feed_taxonomy_items');
            $tables->stand('catalog_feed_builds', 'catalog_feed_build_rejects');
        });

        $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
            $checks->register(FeedsCheck::class);
        });

        // `onOneServer`: two watchers would each see the same feed due.
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command(FeedsCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command(FeedsPruneCommand::class)->dailyAt('04:10')->onOneServer();
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog-feeds.php', 'webx-catalog-feeds');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-feeds');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/public.php');

        $this->reservePrefix();
        $this->registerRateLimiter();

        $this->registerHistory();
        $this->app->make(ModuleRegistry::class)->register($this->app->make(CatalogFeedsModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([FeedsCommand::class, FeedsPruneCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-catalog-feeds.php' => config_path('webx-catalog-feeds.php'),
        ], 'webx-catalog-feeds-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-feeds'),
        ], 'webx-catalog-feeds-lang');
    }

    /** A route with a parameter cannot be asked about one address at a time, so a page could take it. */
    private function reservePrefix(): void
    {
        $config = $this->app->make('config');

        $config->set('webx-routing.reserved', [
            ...(array) $config->get('webx-routing.reserved', []),
            trim((string) $config->get('webx-catalog-feeds.path'), '/'),
        ]);
    }

    /** Hashed: the key is in the path, and it stays out of the cache's keys as of the logs. */
    private function registerRateLimiter(): void
    {
        RateLimiter::for(self::LIMITER, function (Request $request): Limit {
            return Limit::perMinute((int) $this->app->make('config')->get('webx-catalog-feeds.per_minute', 60))
                ->by(hash('sha256', (string) $request->route('key')).'|'.$request->ip());
        });
    }

    /** What the journal shows for a feed: "Template", not `item`. The key is never among them (§13). */
    private function registerHistory(): void
    {
        $fields = [];

        foreach (Feed::HISTORY as $field) {
            $fields[$field] = 'webx-catalog-feeds::feed.'.$field;
        }

        $this->app->make(HistoryTypes::class)->register(
            Feed::TYPE,
            Feed::class,
            fields: $fields,
            permission: ['catalog.view', 'catalog.manage', 'catalog.delete'],
            module: 'catalog-feeds',
            label: 'webx-catalog-feeds::module.feed',
        );
    }
}
