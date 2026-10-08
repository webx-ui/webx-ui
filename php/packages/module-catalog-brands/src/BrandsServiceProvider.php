<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Categories\CategoryLinkSource;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Catalog\Bulk\BulkActions;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Exchange\ExchangeColumns;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\ProductColumns;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Rendering\ProductQuery;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogBrands\Catalog\BrandAction;
use WebxUi\CatalogBrands\Catalog\BrandColumn;
use WebxUi\CatalogBrands\Catalog\BrandDocument;
use WebxUi\CatalogBrands\Catalog\BrandExchangeColumn;
use WebxUi\CatalogBrands\Catalog\BrandFacet;
use WebxUi\CatalogBrands\Catalog\BrandLine;
use WebxUi\CatalogBrands\Catalog\BrandPart;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogBrands\Panel\BrandsModule;
use WebxUi\CatalogBrands\Storefront\BrandHandler;
use WebxUi\CatalogBrands\Storefront\BrandMisses;
use WebxUi\CatalogBrands\Storefront\BrandsController;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Routing\Formatters\Prefixed;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\Misses;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Seo\Sitemap\SitemapRoutes;

/**
 * Brands (§2.3 of the dictionaries spec): a satellite of the catalogue whose records have pages.
 * What it registers in the core's registries is what every satellite does (§3) — the part of the
 * product form, the column, the facet, the document, a bulk action, the line on a card and beside
 * the buy button, `products()->brand()` — and what an entity with a page adds elsewhere: a type of
 * address with its misses, the list of brands as a route, a kind of journal entry, a source of
 * links for a menu, and `brands()` for a template.
 */
class BrandsServiceProvider extends ServiceProvider
{
    /** Where the brands answer under the panel's API, and what the form's field names them by. */
    public const SOURCE = 'catalog/brands';

    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('catalog_brands', 'catalog_product_brand');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog-brands.php', 'webx-catalog-brands');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-brands');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-catalog-brands');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerAddresses();
        $this->registerScreens();
        $this->registerHistory();
        $this->registerCatalog();
        $this->registerQuery();
        $this->registerStorefront();

        $this->app->make(LinkSources::class)->register(new CategoryLinkSource(
            Brand::class,
            Brand::TYPE,
            static fn (): string => (string) __('webx-catalog-brands::module.title'),
            'star',
            302,
        ));

        $this->app->make(ModuleRegistry::class)->register($this->app->make(BrandsModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-catalog-brands.php' => config_path('webx-catalog-brands.php'),
        ], 'webx-catalog-brands-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-brands'),
        ], 'webx-catalog-brands-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-catalog-brands'),
        ], 'webx-catalog-brands-views');
    }

    /**
     * `/brands/{slug}/`, the filter's tail behind it as behind a category. A slug is chosen by
     * hand, so a taken one fails under the field — with whoever holds it — rather than becoming
     * `-2`. A deleted brand's address is a 410, which only {@see BrandMisses} can say.
     */
    private function registerAddresses(): void
    {
        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: Brand::TYPE,
            model: Brand::class,
            formatter: new Prefixed(Brand::prefix(), Slug::class),
            handler: BrandHandler::class,
            acceptsTail: true,
            onConflict: OnConflict::Fail,
        ));

        $this->app->make(Misses::class)->register(BrandMisses::class);
    }

    /**
     * The editor of a brand, and the field of the product form (§3): one choice with a search in
     * the «Main» tab, after the categories. The choices are the brands the panel reads from
     * `props.source`; the server checks the id against the table in the part's rules.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);
        $screens->register(Brand::SCREEN, __DIR__.'/../resources/screens/brand-form.json');

        $screens->extend(Product::SCREEN, [[
            'op' => 'add',
            'target' => 'main',
            'position' => 'after:placement',
            'node' => [
                'id' => 'brand-card',
                'type' => 'wx-card',
                'label' => 'trans::webx-catalog-brands::product.brand',
                'children' => [[
                    'id' => 'brand-id',
                    'type' => 'wx-select',
                    'name' => BrandPart::KEY.'.id',
                    // No label of its own: the card above already says what this is.
                    'help' => 'trans::webx-catalog-brands::product.brand-help',
                    'props' => [
                        'source' => self::SOURCE,
                        'filterable' => true,
                        'clearable' => true,
                        'placeholder' => 'trans::webx-catalog-brands::product.brand-empty',
                    ],
                ]],
            ],
        ]]);

        $this->app->make(CategorySources::class)->register(self::SOURCE, Brand::class, 'webx-catalog-brands::errors.unknown');
    }

    /**
     * What the journal shows for a brand: "Name", not `title`. Read behind any of the catalogue's
     * permissions — whoever may open the section may see who changed it.
     */
    private function registerHistory(): void
    {
        $fields = [];

        foreach (Brand::HISTORY as $field) {
            $fields[$field] = 'webx-catalog-brands::brand.'.$field;
        }

        $this->app->make(HistoryTypes::class)->register(
            Brand::TYPE,
            Brand::class,
            fields: $fields,
            permission: ['catalog.view', 'catalog.manage', 'catalog.delete'],
            module: 'catalog-brands',
            label: 'webx-catalog-brands::module.brand',
        );
    }

    private function registerCatalog(): void
    {
        $this->app->make(ProductParts::class)->register(new BrandPart);
        $this->app->make(ProductColumns::class)->register(new BrandColumn);
        // The column of exchange files (§7.2 of the exchange spec).
        $this->app->make(ExchangeColumns::class)->register(new BrandExchangeColumn);
        $this->app->make(Facets::class)->register(new BrandFacet);
        $this->app->make(Documents::class)->register(new BrandDocument);
        $this->app->make(BulkActions::class)->register(new BrandAction);

        $parts = $this->app->make(StorefrontParts::class);
        $parts->register(new BrandLine('catalog.card.meta'));
        $parts->register(new BrandLine('catalog.product.aside'));
    }

    /**
     * `products()->brand('apple')` — the products of any of these brands, by slug in the language
     * read in or by id. Only published brands: a hidden brand is off the site, and so is the list
     * of its products. A slug nobody has matches no product rather than all of them.
     */
    private function registerQuery(): void
    {
        ProductQuery::macro('brand', function (int|string ...$brands): ProductQuery {
            /** @var ProductQuery $this */
            $brands = array_values(array_filter(
                array_map(static fn (int|string $brand): string => trim((string) $brand), $brands),
                static fn (string $brand): bool => $brand !== '',
            ));

            if ($brands === []) {
                return $this;
            }

            return $this->narrowedBy('brand', static function (Builder $query, string $locale) use ($brands): void {
                $ids = Brand::query()->visible()
                    ->where(static function (Builder $any) use ($brands, $locale): void {
                        foreach ($brands as $brand) {
                            $any->orWhere(static fn (Builder $one): Builder => $one->whereTranslation('slug', $brand, $locale));

                            if (ctype_digit($brand)) {
                                $any->orWhere('catalog_brands.id', (int) $brand);
                            }
                        }
                    })
                    ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

                $query->whereIn($query->getModel()->qualifyColumn('id'), $query->getQuery()->newQuery()
                    ->from(Brand::LINKS)
                    ->select('product_id')
                    ->whereIn('brand_id', $ids === [] ? [0] : array_values($ids)));
            });
        });
    }

    /**
     * The list of brands, `/brands/`: an ordinary route, so it wins before the registry's fallback
     * is reached and `Reserved` closes its address to pages — the catalogue's root is registered
     * the same way. Where the site puts the language in the path, it is registered twice.
     */
    private function registerStorefront(): void
    {
        $prefix = Brand::prefix();

        if ($prefix === '') {
            return;
        }

        /** @var list<string> $middleware */
        $middleware = array_values((array) $this->config()->get('webx-catalog.middleware', ['web', 'webx.locale']));

        Route::get($prefix, [BrandsController::class, 'index'])->middleware($middleware)->name('webx.catalog-brands.index');

        if ((string) $this->config()->get('webx-localization.strategy', 'prefix') === 'prefix') {
            Route::get('{'.OneSpellingPerAddress::PARAMETER.'}/'.$prefix, [BrandsController::class, 'index'])
                ->middleware([...$middleware, OneSpellingPerAddress::class])
                ->name('webx.catalog-brands.index.localised');
        }

        $this->app->make(SitemapRoutes::class)->register('webx.catalog-brands.index');
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
