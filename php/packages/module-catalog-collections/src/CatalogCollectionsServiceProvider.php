<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * Product collections of the catalogue (`WEBX_UI_CATALOG_COLLECTIONS.md`): a satellite that knows
 * only the core. What it registers so far: the tables, what moves between stands and the journal.
 */
class CatalogCollectionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // A collection is content: it moves between stands with webx:snapshot.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('catalog_collections', ProductCollection::PRODUCTS);
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog-collections.php', 'webx-catalog-collections');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-collections');

        $this->registerHistory();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-catalog-collections.php' => config_path('webx-catalog-collections.php'),
        ], 'webx-catalog-collections-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-collections'),
        ], 'webx-catalog-collections-lang');
    }

    /**
     * What the journal shows for a collection: "Set", not `filters`. Read behind any of the
     * catalogue's permissions.
     */
    private function registerHistory(): void
    {
        $fields = [];

        foreach (ProductCollection::HISTORY as $field) {
            $fields[$field] = 'webx-catalog-collections::collection.'.$field;
        }

        $this->app->make(HistoryTypes::class)->register(
            ProductCollection::TYPE,
            ProductCollection::class,
            fields: $fields,
            permission: ['catalog.view', 'catalog.manage', 'catalog.delete'],
            module: 'catalog-collections',
            label: 'webx-catalog-collections::module.collection',
        );
    }
}
