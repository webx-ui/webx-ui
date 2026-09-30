<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Bulk\BulkActions;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\ProductColumns;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Rendering\ProductQuery;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogStock\Catalog\Stock;
use WebxUi\CatalogStock\Catalog\StockAction;
use WebxUi\CatalogStock\Catalog\StockColumn;
use WebxUi\CatalogStock\Catalog\StockDocument;
use WebxUi\CatalogStock\Catalog\StockFacet;
use WebxUi\CatalogStock\Catalog\StockLine;
use WebxUi\CatalogStock\Catalog\StockPart;
use WebxUi\CatalogStock\Catalog\StockRule;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\Panel\StockModule;

/**
 * Stock (§2.2 of the dictionaries spec): a satellite of the catalogue, and almost all of it is
 * registrations in the core's registries (§3) — the part of the product form, the column of the
 * list, the facet, the fields of the document, the refusal to sell, a bulk action, the line on a
 * card and beside the buy button, and `products()->inStock()` — plus the panel's shared category
 * screens for the list itself.
 */
class StockServiceProvider extends ServiceProvider
{
    /** Where the statuses answer under the panel's API, and what the form's field names them by. */
    public const SOURCE = 'catalog/stock';

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-stock');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-catalog-stock');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerScreens();
        $this->registerCatalog();
        $this->registerQuery();
        $this->app->make(ModuleRegistry::class)->register($this->app->make(StockModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-stock'),
        ], 'webx-catalog-stock-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-catalog-stock'),
        ], 'webx-catalog-stock-views');
    }

    /**
     * The editor of a status, and the field of the product form (§3): one choice in the «Main» tab,
     * right under the price, since whether it can be bought is read together with what it costs. The choices are the statuses the panel reads from `props.source`; the
     * server checks the id against the table in the part's rules.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);
        $screens->register(StockStatus::SCREEN, __DIR__.'/../resources/screens/stock-status-form.json');

        $screens->extend(Product::SCREEN, [[
            'op' => 'add',
            'target' => 'main',
            'position' => 'after:pricing',
            'node' => [
                'id' => 'stock-card',
                'type' => 'wx-card',
                'label' => 'trans::webx-catalog-stock::module.title',
                'children' => [[
                    'id' => 'stock-status',
                    'type' => 'wx-select',
                    'name' => StockPart::KEY.'.status',
                    // No label of its own: the card above already says what this is.
                    'help' => 'trans::webx-catalog-stock::product.status-help',
                    'props' => [
                        'source' => self::SOURCE,
                        'clearable' => true,
                        'placeholder' => 'trans::webx-catalog-stock::product.status-empty',
                    ],
                ]],
            ],
        ]]);

        $this->app->make(CategorySources::class)->register(self::SOURCE, StockStatus::class, 'webx-catalog-stock::errors.unknown');
    }

    /**
     * The refusal is registered in the ordinary order: after the core's "not on sale", before its
     * "price on request", which the core registers as the last word whenever this boots.
     */
    private function registerCatalog(): void
    {
        $this->app->make(ProductParts::class)->register(new StockPart);
        $this->app->make(ProductColumns::class)->register(new StockColumn);
        $this->app->make(Facets::class)->register(new StockFacet);
        $this->app->make(Documents::class)->register(new StockDocument);
        $this->app->make(Purchasability::class)->register(new StockRule);
        $this->app->make(BulkActions::class)->register(new StockAction);

        $parts = $this->app->make(StorefrontParts::class);
        $parts->register(new StockLine('catalog.card.meta'));
        $parts->register(new StockLine('catalog.product.aside'));
    }

    /**
     * `products()->inStock()` — the products whose status can be bought, a product without a row
     * by its default status.
     */
    private function registerQuery(): void
    {
        ProductQuery::macro('inStock', function (): ProductQuery {
            /** @var ProductQuery $this */
            return $this->narrowedBy('in-stock', static function (Builder $query): void {
                $ids = StockStatus::query()->where('is_purchasable', true)->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)->all();

                Stock::whereIn($query, array_values($ids));
            });
        });
    }
}
