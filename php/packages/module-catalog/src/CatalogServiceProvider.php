<?php

declare(strict_types=1);

namespace WebxUi\Catalog;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Types\OptionType;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Catalog\Bulk\Actions\ExtraCategoryAction;
use WebxUi\Catalog\Bulk\Actions\PublicationAction;
use WebxUi\Catalog\Bulk\Actions\SetCategoryAction;
use WebxUi\Catalog\Bulk\Actions\TrashAction;
use WebxUi\Catalog\Bulk\BulkActions;
use WebxUi\Catalog\Collections\ProductsSource;
use WebxUi\Catalog\Console\ExchangePruneCommand;
use WebxUi\Catalog\Console\FlushViewsCommand;
use WebxUi\Catalog\Console\IndexCommand;
use WebxUi\Catalog\Console\PopularityCommand;
use WebxUi\Catalog\Doctor\EngineCheck;
use WebxUi\Catalog\Doctor\ExchangeCheck;
use WebxUi\Catalog\Doctor\RootCheck;
use WebxUi\Catalog\Documents\CoreDocument;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Engine\CatalogEngines;
use WebxUi\Catalog\Engine\SqlEngine;
use WebxUi\Catalog\Exchange\Columns\CategoryColumn;
use WebxUi\Catalog\Exchange\Columns\ExternalIdColumn;
use WebxUi\Catalog\Exchange\Columns\IdColumn;
use WebxUi\Catalog\Exchange\Columns\ImagesColumn;
use WebxUi\Catalog\Exchange\Columns\ValueColumn;
use WebxUi\Catalog\Exchange\ExchangeColumns;
use WebxUi\Catalog\Exchange\ExchangeFiles;
use WebxUi\Catalog\Exchange\ExchangeFormats;
use WebxUi\Catalog\Exchange\Formats\CsvFormat;
use WebxUi\Catalog\Exchange\Formats\XlsxFormat;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\PriceFacet;
use WebxUi\Catalog\Filter\FilterAliases;
use WebxUi\Catalog\Filter\FilterSerializer;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Catalog\Filter\SegmentSerializer;
use WebxUi\Catalog\Gallery\Gallery;
use WebxUi\Catalog\Gallery\Video\VideoProviders;
use WebxUi\Catalog\Gallery\Video\YouTubeProvider;
use WebxUi\Catalog\Http\Controllers\StorefrontController;
use WebxUi\Catalog\Links\CategoryLinkSource;
use WebxUi\Catalog\Links\ProductLinkSource;
use WebxUi\Catalog\Mcp\SatelliteTools;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\CatalogCategoriesModule;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\Catalog\Panel\CategoryFieldType;
use WebxUi\Catalog\Panel\FacetsFieldType;
use WebxUi\Catalog\Panel\ProductColumns;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Popularity\PopularityFormula;
use WebxUi\Catalog\Popularity\PopularitySignals;
use WebxUi\Catalog\Popularity\ViewsSignal;
use WebxUi\Catalog\Purchase\CoreRules;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Routing\CatalogMisses;
use WebxUi\Catalog\Routing\CategoryHandler;
use WebxUi\Catalog\Routing\ProductHandler;
use WebxUi\Catalog\Search\SearchContributors;
use WebxUi\Catalog\Seo\FilterSitemap;
use WebxUi\Catalog\Seo\ListingSource;
use WebxUi\Catalog\Seo\UnavailableSource;
use WebxUi\Catalog\Sorts\CoreSort;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\Formatters\SlugId;
use WebxUi\Routing\Misses;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Rendering\SeoSources;
use WebxUi\Seo\Sitemap\SitemapRoutes;
use WebxUi\Seo\Sitemap\SitemapSources;

/**
 * The core of the catalogue: products, a tree of categories, the gallery, the engine and the
 * storefront, and the registries its satellites plug into (§7). Most of what it does is
 * registrations — in its own registries and in somebody else's: two kinds of address, the answers
 * to addresses nobody holds any more, two screens, two field types, two kinds of journal entry,
 * two SEO sources, a file of the sitemap, a doctor's check, a purpose of chunked uploads and a
 * section of the panel.
 */
class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog.php', 'webx-catalog');

        // The registries satellites fill from their own providers; the core fills them first.
        $this->app->singleton(ProductParts::class);
        $this->app->singleton(BulkActions::class);
        $this->app->singleton(Facets::class);
        $this->app->singleton(Sorts::class);
        $this->app->singleton(Documents::class);
        $this->app->singleton(ProductColumns::class);
        $this->app->singleton(Purchasability::class);
        $this->app->singleton(PopularitySignals::class);
        $this->app->singleton(CatalogEngines::class);
        $this->app->singleton(StorefrontParts::class);
        $this->app->singleton(FilterUrls::class);
        $this->app->singleton(VideoProviders::class);
        $this->app->singleton(SearchContributors::class);
        $this->app->singleton(FilterAliases::class);
        $this->app->singleton(SatelliteTools::class);
        $this->app->singleton(ExchangeColumns::class);
        $this->app->singleton(ExchangeFormats::class);

        $this->app->singleton(Catalog::class);
        $this->app->singleton(CategoryFacets::class);
        $this->app->singleton(CategoryFacet::class);
        $this->app->bindIf(FilterSerializer::class, SegmentSerializer::class, shared: true);
        $this->app->bindIf(PopularityFormula::class, PopularityFormula::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-catalog');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerAddresses();
        $this->registerScreens();
        $this->registerHistory();
        $this->registerPanel();
        $this->registerEngine();
        $this->registerStorefront();
        $this->registerBulk();
        $this->registerVideo();
        $this->registerExchange();
        $this->registerSources();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([IndexCommand::class, FlushViewsCommand::class, PopularityCommand::class, ExchangePruneCommand::class]);
        $this->registerSchedule();

        $this->publishes([
            __DIR__.'/../config/webx-catalog.php' => config_path('webx-catalog.php'),
        ], 'webx-catalog-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog'),
        ], 'webx-catalog-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-catalog'),
        ], 'webx-catalog-views');
    }

    /**
     * Two types at the root of the site (decisions 23 and 24): a category is `/{slug}`, flat at
     * any depth; a product is `/{slug}-{id}`.
     *
     * A category's slug is chosen by hand, so a taken one fails under the field rather than
     * becoming `-2`. A product's cannot collide with another product — the id makes it unique —
     * only with a page that happens to be called `name-12`, and an import must not stop on that:
     * the product takes a suffix instead.
     *
     * What the registry does not hold — another spelling of a product, a deleted product or
     * category — is answered by {@see CatalogMisses}.
     */
    private function registerAddresses(): void
    {
        $types = $this->app->make(RouteTypes::class);

        $types->register(new RouteType(
            type: Category::TYPE,
            model: Category::class,
            formatter: Slug::class,
            handler: CategoryHandler::class,
            acceptsTail: true,
            onConflict: OnConflict::Fail,
        ));

        $types->register(new RouteType(
            type: Product::TYPE,
            model: Product::class,
            formatter: SlugId::class,
            handler: ProductHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Suffix,
        ));

        $this->app->make(Misses::class)->register(CatalogMisses::class);
    }

    /**
     * Both editors are described screens, so a satellite adds its tab with a patch. Switched-off
     * fields are taken off here, once, so that neither the form nor the save ever sees them —
     * the screen is what the server validates against, and a field that is not on it is a field
     * nobody can write.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Product::SCREEN, __DIR__.'/../resources/screens/product-form.json');
        $screens->register(Category::SCREEN, __DIR__.'/../resources/screens/category-form.json');
        // The exchange's two forms (§8.1 of the exchange spec): how an import writes, and the head
        // of a saved profile. Nothing saves them as a record — the panel sends their values as the
        // options of a run or a profile — but described, a project can take a setting away.
        $screens->register('catalog.exchange-import', __DIR__.'/../resources/screens/exchange-import.json');
        $screens->register('catalog.exchange-profile', __DIR__.'/../resources/screens/exchange-profile.json');

        $units = array_values(array_map('strval', (array) $this->config()->get('webx-catalog.units', [])));
        $patch = [[
            'op' => 'set',
            'target' => 'unit',
            'props' => ['options' => array_map(
                static fn (string $unit): array => ['label' => 'trans::webx-catalog::units.'.$unit, 'value' => $unit],
                $units,
            )],
        ]];

        if (! $this->priced()) {
            $patch[] = ['op' => 'remove', 'target' => 'prices'];
        }

        if (! (bool) $this->config()->get('webx-catalog.fields.barcode', true)) {
            $patch[] = ['op' => 'remove', 'target' => 'barcode-col'];
        }

        // What the gallery field needs to offer videos (§7 of the video spec): the flag, and the
        // limits it checks a file against before sending a byte of it.
        $patch[] = [
            'op' => 'set',
            'target' => 'gallery',
            'props' => [
                'video' => (bool) $this->config()->get('webx-catalog.fields.video', true),
                'videoTypes' => array_values(array_map('strval', (array) $this->config()->get('webx-catalog.videos.types', []))),
                'videoMaxBytes' => max(1, (int) $this->config()->get('webx-catalog.videos.max_size_mb', 2048)) * 1048576,
            ],
        ];

        $screens->extend(Product::SCREEN, $patch);

        if (! (bool) $this->config()->get('webx-catalog.fields.facets', false)) {
            $screens->extend(Category::SCREEN, [['op' => 'remove', 'target' => 'filters-tab']]);
        }

        $fields = $this->app->make(FieldTypes::class);
        $fields->register('wx-catalog-category', new CategoryFieldType);
        $fields->register('wx-catalog-facets', new FacetsFieldType);
        // The tone of a reference book's record (labels, stock): one of the screen's options, drawn
        // by the panel as a tag of that tone rather than as a word.
        $fields->register('wx-catalog-tone', new OptionType);
    }

    /**
     * What the journal shows for a product and a category: "Price", not `price` (§11.3). Read
     * behind any of the three permissions — whoever may open the section may see who changed it.
     */
    private function registerHistory(): void
    {
        $types = $this->app->make(HistoryTypes::class);
        $permissions = ['catalog.view', 'catalog.manage', 'catalog.delete'];

        $product = [];

        foreach (['name', 'slug', 'sku', 'barcode', 'summary', 'description', 'category_id', 'categories', 'price', 'old_price', 'unit', 'priority', 'is_published', 'images'] as $field) {
            $product[$field] = 'webx-catalog::product.'.$field;
        }

        $types->register(Product::TYPE, Product::class, fields: $product, permission: $permissions, module: 'catalog', label: 'webx-catalog::module.product');

        $category = [];

        foreach (['name', 'slug', 'description', 'cover_id', 'is_published', 'parent', 'facets'] as $field) {
            $category[$field] = 'webx-catalog::category.'.$field;
        }

        $types->register(Category::TYPE, Category::class, fields: $category, permission: $permissions, module: 'catalog', label: 'webx-catalog::module.category');
    }

    /**
     * The group is added to the panel's config at boot rather than shipped as a default: a site
     * that published `webx-admin.php` has its own copy of the list (CLAUDE.md §4). So is its
     * «Dictionaries» caption, which the satellites' reference lists stand under — into a group
     * the site wrote itself as well, since that copy predates the caption.
     */
    private function registerPanel(): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $this->config()->get('webx-admin.groups', []);
        $group = $groups[CatalogModule::GROUP] ?? ['title' => 'webx-catalog::module.group', 'icon' => 'cart', 'order' => 300];

        if (is_array($group)) {
            $sections = is_array($group['sections'] ?? null) ? $group['sections'] : [];
            $sections[CatalogModule::DICTIONARIES] ??= ['title' => 'webx-catalog::module.dictionaries', 'order' => 100];
            $group['sections'] = $sections;
        }

        $this->config()->set('webx-admin.groups', [...$groups, CatalogModule::GROUP => $group]);

        $modules = $this->app->make(ModuleRegistry::class);
        $modules->register($this->app->make(CatalogModule::class));
        $modules->register(new CatalogCategoriesModule);
    }

    /**
     * The core's entries in its own registries (§7), first, so they lead every list: the facets
     * category and price, the sorts, the core's share of the document, the refusals to sell, the
     * views as a signal, the database as an engine.
     *
     * Switched-off prices are not registered at all rather than hidden: no facet, no sort, no
     * field in the document, no refusal — a site without prices has no trace of them.
     *
     * "Price on request" is registered as the last word of the chain: a satellite's "out of
     * stock" says more.
     */
    private function registerEngine(): void
    {
        $facets = $this->app->make(Facets::class);
        $facets->register($this->app->make(CategoryFacet::class));

        if ($this->priced()) {
            $facets->register(new PriceFacet);
        }

        $sorts = $this->app->make(Sorts::class);
        $sorts->register(CoreSort::default((array) $this->config()->get('webx-catalog.default_sort', [])));

        if ($this->priced()) {
            $sorts->register(new CoreSort('price_asc', ['price' => 'asc']));
            $sorts->register(new CoreSort('price_desc', ['price' => 'desc']));
        }

        $sorts->register(new CoreSort('popular', ['score' => 'desc', 'created_at' => 'desc']));
        $sorts->register(new CoreSort('new', ['created_at' => 'desc']));
        $sorts->register(new CoreSort('name', ['name' => 'asc']));

        $this->app->make(Documents::class)->register($this->app->make(CoreDocument::class));
        $this->app->make(PopularitySignals::class)->register(new ViewsSignal);
        $this->app->make(CatalogEngines::class)->register('sql', SqlEngine::class);

        $rules = $this->app->make(CoreRules::class);
        $purchasability = $this->app->make(Purchasability::class);
        $purchasability->register($rules->onSale());
        $purchasability->register($rules->priced(), last: true);

        $this->app->make(DoctorChecks::class)->register(EngineCheck::class);
        $this->app->make(DoctorChecks::class)->register(ExchangeCheck::class);
        $this->app->make(DoctorChecks::class)->register(RootCheck::class);

        // A category moved to another branch inherits another branch's facets; nested-set moves
        // without an `updated`, and says so with an event of its own.
        $this->app->make(Dispatcher::class)->listen('eloquent.moved: '.Category::class, function (): void {
            $this->app->make(CategoryFacets::class)->forget();
        });

        // The sources' facets are kept for a request; a worker's next job is another request.
        $this->app->make(Dispatcher::class)->listen(JobProcessing::class, function (): void {
            $this->app->make(Facets::class)->flush();
        });
    }

    /**
     * The storefront's SEO and its two routes (§4, §10): the search always, the root of the
     * catalogue only when it is switched on (decision 25 of the architecture).
     *
     * Ordinary routes, so they win before the registry's fallback is reached and `Reserved`
     * closes their addresses to pages. The search is registered first: the root takes a tail,
     * and `/catalog/search` must not be read as a filter of the root. Where the site puts the
     * language in the path, each is registered twice, as the blog's are.
     */
    private function registerStorefront(): void
    {
        $sources = $this->app->make(SeoSources::class);
        $sources->register(new UnavailableSource);
        $sources->register(new ListingSource);

        $this->app->make(SitemapSources::class)->register($this->app->make(FilterSitemap::class));

        $prefix = UrlNormaliser::key((string) $this->config()->get('webx-catalog.root.prefix', 'catalog'));

        if ($prefix === '') {
            return;
        }

        $routes = [['path' => $prefix.'/search/{tail?}', 'action' => 'search', 'name' => 'webx.catalog.search']];

        if ((bool) $this->config()->get('webx-catalog.root.enabled', false)) {
            $routes[] = ['path' => $prefix.'/{tail?}', 'action' => 'root', 'name' => 'webx.catalog.root'];
            $this->app->make(SitemapRoutes::class)->register('webx.catalog.root');
        }

        /** @var list<string> $middleware */
        $middleware = array_values((array) $this->config()->get('webx-catalog.middleware', ['web', 'webx.locale']));
        $localised = (string) $this->config()->get('webx-localization.strategy', 'prefix') === 'prefix';

        foreach ($routes as $route) {
            Route::get($route['path'], [StorefrontController::class, $route['action']])
                ->where(['tail' => '.*'])
                ->middleware($middleware)
                ->name($route['name']);

            if ($localised) {
                Route::get('{'.OneSpellingPerAddress::PARAMETER.'}/'.$route['path'], [StorefrontController::class, $route['action']])
                    ->where(['tail' => '.*'])
                    ->middleware([...$middleware, OneSpellingPerAddress::class])
                    ->name($route['name'].'.localised');
            }
        }
    }

    /**
     * The core's bulk actions (§11.4), first in the list; satellites add theirs — a label, a stock
     * status — to the same registry from their own providers.
     */
    private function registerBulk(): void
    {
        $actions = $this->app->make(BulkActions::class);

        $actions->register(new PublicationAction(true));
        $actions->register(new PublicationAction(false));
        $actions->register(new SetCategoryAction);
        $actions->register(new ExtraCategoryAction(true));
        $actions->register(new ExtraCategoryAction(false));
        $actions->register(new TrashAction(true));
        $actions->register(new TrashAction(false));
    }

    /**
     * Videos in the gallery (the video spec): YouTube as the first provider, and the purpose a
     * file is uploaded in pieces under. The limits are closures, read on every upload, so that a
     * site or a test changes them by config. Switched off, the purpose stays: the endpoint that
     * attaches refuses, and an upload started before the switch is swept by its TTL.
     */
    private function registerVideo(): void
    {
        $this->app->make(VideoProviders::class)->register(new YouTubeProvider);

        $this->app->make(UploadPurposes::class)->register(
            Gallery::UPLOAD_PURPOSE,
            permission: 'catalog.manage',
            types: fn (): array => array_values(array_map('strval', (array) $this->config()->get('webx-catalog.videos.types', ['video/mp4', 'video/webm']))),
            maxBytes: fn (): int => max(1, (int) $this->config()->get('webx-catalog.videos.max_size_mb', 2048)) * 1048576,
        );
    }

    /**
     * The exchange (the exchange spec): CSV and XLSX, the core's columns in the order a file
     * shows them (§7.1), and the purpose a file is uploaded in pieces under. A switched-off field
     * has no column, as it has no field on the form. The upload takes any type: the extension
     * decides the format, and the reader refuses what it cannot read.
     */
    private function registerExchange(): void
    {
        $formats = $this->app->make(ExchangeFormats::class);
        $formats->register(new CsvFormat);
        $formats->register(new XlsxFormat);

        $columns = $this->app->make(ExchangeColumns::class);
        $columns->register(new IdColumn);
        $columns->register(new ValueColumn('sku'));

        if ((bool) $this->config()->get('webx-catalog.fields.barcode', true)) {
            $columns->register(new ValueColumn('barcode'));
        }

        $columns->register(new ExternalIdColumn);

        foreach (['name', 'slug', 'summary', 'description'] as $translated) {
            $columns->register(new ValueColumn($translated, localized: true));
        }

        $columns->register(new CategoryColumn);
        $columns->register(new CategoryColumn(multiple: true));

        if ($this->priced()) {
            $columns->register(new ValueColumn('price', ValueColumn::DECIMAL));
            $columns->register(new ValueColumn('old_price', ValueColumn::DECIMAL));
        }

        $columns->register(new ValueColumn('unit', ValueColumn::UNIT));
        $columns->register(new ValueColumn('priority', ValueColumn::INTEGER));
        $columns->register(new ValueColumn('is_published', ValueColumn::BOOLEAN));
        $columns->register(new ImagesColumn);

        $this->app->make(UploadPurposes::class)->register(
            ExchangeFiles::UPLOAD_PURPOSE,
            permission: 'catalog.manage',
            maxBytes: fn (): int => max(1, (int) $this->config()->get('webx-catalog.exchange.max_bytes', 200 * 1024 * 1024)),
        );
    }

    /**
     * What the rest of the panel reaches the catalogue by (§14): categories and products for a
     * menu or a link field, and products for a `wx-collection` block. From the provider rather
     * than the routes file, which `route:cache` never runs.
     */
    private function registerSources(): void
    {
        $links = $this->app->make(LinkSources::class);
        $links->register(new CategoryLinkSource);
        $links->register(new ProductLinkSource);

        $this->app->make(CollectionSources::class)->register(new ProductsSource);
    }

    /**
     * The worker every minute where the engine keeps an index, the views every five minutes, the
     * recount nightly — put on the schedule by the package, as the frame's backup is. `onOneServer`:
     * two workers on one queue would each index half of it twice.
     */
    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if ($this->app->make(Catalog::class)->needsIndex()) {
                $schedule->command(IndexCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
            }

            if ((bool) $this->config()->get('webx-catalog.popularity.views', true)) {
                $schedule->command(FlushViewsCommand::class)->everyFiveMinutes()->onOneServer();
            }

            $schedule->command(PopularityCommand::class)->dailyAt('03:30')->onOneServer();
            $schedule->command(ExchangePruneCommand::class)->hourly()->onOneServer();
        });
    }

    private function priced(): bool
    {
        return (bool) $this->config()->get('webx-catalog.price.enabled', true);
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
