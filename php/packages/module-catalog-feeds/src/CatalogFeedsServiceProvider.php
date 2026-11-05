<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
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

    public function register(): void
    {
        // A feed's settings are content; its builds are what happened on this stand, and their
        // files stay here, so a restored feed answers 404 until it is built again.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('catalog_feeds', 'catalog_feed_exclusions', 'catalog_feed_categories', 'catalog_feed_taxonomies', 'catalog_feed_taxonomy_items');
            $tables->stand('catalog_feed_builds', 'catalog_feed_build_rejects');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog-feeds.php', 'webx-catalog-feeds');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-feeds');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerHistory();
        $this->app->make(ModuleRegistry::class)->register($this->app->make(CatalogFeedsModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-catalog-feeds.php' => config_path('webx-catalog-feeds.php'),
        ], 'webx-catalog-feeds-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-feeds'),
        ], 'webx-catalog-feeds-lang');
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
