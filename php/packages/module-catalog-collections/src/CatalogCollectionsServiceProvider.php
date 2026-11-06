<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Events\FacetValueRetargeted;
use WebxUi\CatalogCollections\Catalog\CollectionPreset;
use WebxUi\CatalogCollections\Catalog\CollectionPresets;
use WebxUi\CatalogCollections\Catalog\CollectionRepairs;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Resolvers\CollectionResolvers;
use WebxUi\CatalogCollections\Resolvers\FilterResolver;
use WebxUi\CatalogCollections\Resolvers\ManualResolver;
use WebxUi\CatalogCollections\Storefront\LiveCollections;

/**
 * Product collections of the catalogue (`WEBX_UI_CATALOG_COLLECTIONS.md`): a satellite that knows
 * only the core. What it registers so far: the tables, what moves between stands, the journal, what
 * a collection holds, its cache and the tag that prints it.
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

        $this->app->singleton(CollectionResolvers::class);
        $this->app->singleton(CollectionPresets::class);

        $this->callAfterResolving(CollectionResolvers::class, function (CollectionResolvers $resolvers): void {
            $resolvers->register($this->app->make(ManualResolver::class));
            $resolvers->register($this->app->make(FilterResolver::class));
        });

        $this->callAfterResolving(CollectionPresets::class, static function (CollectionPresets $presets): void {
            $presets->register(new CollectionPreset(CollectionPresets::NEW, sort: 'new'));
            $presets->register(new CollectionPreset(CollectionPresets::ON_SALE, onSale: true));
            $presets->register(new CollectionPreset(CollectionPresets::LABEL, facet: 'label'));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-collections');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-catalog-collections');

        Blade::componentNamespace('WebxUi\\CatalogCollections\\View\\Components', 'webx-catalog-collections');

        $this->registerHistory();
        $this->registerCache();

        $this->app->make(Dispatcher::class)->listen(FacetValueRetargeted::class, [CollectionRepairs::class, 'handle']);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-catalog-collections.php' => config_path('webx-catalog-collections.php'),
        ], 'webx-catalog-collections-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-collections'),
        ], 'webx-catalog-collections-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-catalog-collections'),
        ], 'webx-catalog-collections-views');
    }

    /**
     * A group of its own and not `StorefrontCache::watch()`: saving a collection must not forget
     * the filter counts of the whole catalogue (§5.5).
     */
    private function registerCache(): void
    {
        $groups = $this->app->make(CacheGroups::class);
        $groups->declare(LiveCollections::GROUP, 'webx-catalog-collections::module.title');
        $groups->bumpOn(LiveCollections::GROUP, ProductCollection::class);
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
