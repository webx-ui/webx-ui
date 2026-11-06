<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Help\HelpRegistry;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Events\FacetValueRetargeted;
use WebxUi\Catalog\Mcp\SatelliteTools;
use WebxUi\Catalog\Rendering\ProductQuery;
use WebxUi\CatalogCollections\Catalog\CollectionPreset;
use WebxUi\CatalogCollections\Catalog\CollectionPresets;
use WebxUi\CatalogCollections\Catalog\CollectionRepairs;
use WebxUi\CatalogCollections\Mcp\CollectionsResource;
use WebxUi\CatalogCollections\Mcp\CollectionTools;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Panel\CollectionsModule;
use WebxUi\CatalogCollections\Relations\CollectionTarget;
use WebxUi\CatalogCollections\Resolvers\CollectionResolvers;
use WebxUi\CatalogCollections\Resolvers\FilterResolver;
use WebxUi\CatalogCollections\Resolvers\ManualResolver;
use WebxUi\CatalogCollections\Screens\CollectionFieldType;
use WebxUi\CatalogCollections\Storefront\CollectionShortcode;
use WebxUi\CatalogCollections\Storefront\LiveCollections;

/**
 * Product collections of the catalogue (`WEBX_UI_CATALOG_COLLECTIONS.md`): a satellite that knows
 * only the core. What it registers so far: the tables, what moves between stands, the journal, what
 * a collection holds, its cache, the tag that prints it, the block that places it, the shortcode
 * that puts it in a text, the section of the panel with its API, and the agent's tools.
 */
class CatalogCollectionsServiceProvider extends ServiceProvider
{
    /** The module's id in the panel: what `webx:setup` and `--module` ask for. */
    public const MODULE = 'catalog-collections';

    /** Where the collections answer under the panel's API. */
    public const SOURCE = 'catalog/collections';

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
        $this->app->make(HelpRegistry::class)->directory(__DIR__.'/../resources/help');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-collections');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-catalog-collections');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        Blade::componentNamespace('WebxUi\\CatalogCollections\\View\\Components', 'webx-catalog-collections');

        $this->registerHistory();
        $this->registerCache();
        $this->registerBlock();
        $this->registerShortcode();
        $this->registerPanel();

        $this->app->make(Dispatcher::class)->listen(FacetValueRetargeted::class, [CollectionRepairs::class, 'handle']);

        // The agent's tools and `catalog://collections` are the catalogue's (§7): its names, scopes
        // and permissions. Built when an agent asks, not at boot.
        $mcp = $this->app->make(SatelliteTools::class);
        $mcp->tools(fn (): array => $this->app->make(CollectionTools::class)->all());
        $mcp->resources(fn (): array => [$this->app->make(CollectionsResource::class)->resource()]);

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
     * A collection is what a block chooses (§4.2): `wx-catalog-collection` keeps its key and refuses
     * one nobody has, the picker lists them through the relation target, and the block itself is
     * what a site installs with `webx:blocks:offered --install`.
     */
    private function registerBlock(): void
    {
        $this->app->make(RelationTargets::class)->register(new CollectionTarget);
        $this->app->make(FieldTypes::class)->register('wx-catalog-collection', new CollectionFieldType);

        if (class_exists(BlockOffers::class) && $this->app->bound(BlockOffers::class)) {
            $this->app->make(BlockOffers::class)->offer(self::MODULE, __DIR__.'/../resources/blocks');
        }
    }

    /**
     * A collection in a rich text (§4.3). `plain: ''` keeps it out of excerpts, meta descriptions,
     * feeds and the search index; the description is what agents read, the panel shows the rows.
     */
    private function registerShortcode(): void
    {
        $shortcode = fn (): CollectionShortcode => $this->app->make(CollectionShortcode::class);

        $this->app->make(Shortcodes::class)->register(
            CollectionShortcode::NAME,
            static fn (array $args): string => $shortcode()->html($args),
            plain: '',
            description: 'A product collection as a section of the page, in a rich text only: [product-collection key=cameras view=carousel limit=4]. Give it a paragraph of its own.',
            block: true,
            menu: static fn (): array => $shortcode()->menu(),
        );
    }

    /**
     * The section and its form. The patch waits for the tree: the ceiling is the site's config, and
     * whether the stock satellite is there is known only once every provider has booted (§6.2).
     */
    private function registerPanel(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);
        $screens->register(ProductCollection::SCREEN, __DIR__.'/../resources/screens/collection-form.json');
        $screens->extend(ProductCollection::SCREEN, static function (): array {
            $max = ProductCollection::maxLimit();
            $patch = [
                ['op' => 'set', 'target' => 'limit', 'props' => ['max' => $max]],
                ['op' => 'set', 'target' => 'products', 'props' => ['max' => $max]],
            ];

            return ProductQuery::hasMacro('inStock') ? $patch : [...$patch, ['op' => 'remove', 'target' => 'only-purchasable']];
        });

        $this->app->make(ModuleRegistry::class)->register($this->app->make(CollectionsModule::class));
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
            module: self::MODULE,
            label: 'webx-catalog-collections::module.collection',
        );
    }
}
