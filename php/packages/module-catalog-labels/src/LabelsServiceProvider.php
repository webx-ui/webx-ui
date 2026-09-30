<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels;

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
use WebxUi\Catalog\Rendering\ProductQuery;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogLabels\Catalog\BadgesPart;
use WebxUi\CatalogLabels\Catalog\LabelAction;
use WebxUi\CatalogLabels\Catalog\LabelFacet;
use WebxUi\CatalogLabels\Catalog\LabelsColumn;
use WebxUi\CatalogLabels\Catalog\LabelsDocument;
use WebxUi\CatalogLabels\Catalog\LabelsPart;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\CatalogLabels\Panel\LabelsModule;

/**
 * Labels (§2.1 of the dictionaries spec): a satellite of the catalogue, and almost all of it is
 * registrations in the core's registries (§3) — the part of the product form, the column of the
 * list, the facet, the field of the document, two bulk actions, the badges of a card and
 * `products()->label()` — plus the panel's shared category screens for the list itself.
 */
class LabelsServiceProvider extends ServiceProvider
{
    /** Where the labels answer under the panel's API, and what `wx-categories` names them by. */
    public const SOURCE = 'catalog/labels';

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-labels');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-catalog-labels');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerScreens();
        $this->registerCatalog();
        $this->registerQuery();
        $this->app->make(ModuleRegistry::class)->register($this->app->make(LabelsModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-labels'),
        ], 'webx-catalog-labels-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-catalog-labels'),
        ], 'webx-catalog-labels-views');
    }

    /**
     * The editor of a label, and the field of the product form (§3): a multiple choice in the
     * «Main» tab, after the categories. The field names the labels by the path they answer at, and
     * this is where that path is told which table the ids live in — from the provider, which
     * `route:cache` does not skip.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);
        $screens->register(Label::SCREEN, __DIR__.'/../resources/screens/label-form.json');

        $screens->extend(Product::SCREEN, [[
            'op' => 'add',
            'target' => 'main',
            'position' => 'after:placement',
            'node' => [
                'id' => 'labels-card',
                'type' => 'wx-card',
                'label' => 'trans::webx-catalog-labels::product.labels',
                'children' => [[
                    'id' => 'labels-ids',
                    'type' => 'wx-categories',
                    'name' => LabelsPart::KEY.'.ids',
                    // No label of its own: the card above already says what this is.
                    'help' => 'trans::webx-catalog-labels::product.labels-help',
                    // No main one: labels have no order on a product, the badges stand in the list's.
                    'props' => [
                        'source' => self::SOURCE,
                        'main' => false,
                        'addText' => 'trans::webx-catalog-labels::product.labels-add',
                        'removeText' => 'trans::webx-catalog-labels::product.labels-remove',
                        'emptyText' => 'trans::webx-catalog-labels::product.labels-empty',
                        'noneLeftText' => 'trans::webx-catalog-labels::product.labels-none-left',
                    ],
                ]],
            ],
        ]]);

        $this->app->make(CategorySources::class)->register(self::SOURCE, Label::class, 'webx-catalog-labels::errors.unknown');
    }

    private function registerCatalog(): void
    {
        $this->app->make(ProductParts::class)->register(new LabelsPart);
        $this->app->make(ProductColumns::class)->register(new LabelsColumn);
        $this->app->make(Facets::class)->register(new LabelFacet);
        $this->app->make(Documents::class)->register(new LabelsDocument);
        $this->app->make(StorefrontParts::class)->register(new BadgesPart);

        $actions = $this->app->make(BulkActions::class);
        $actions->register(new LabelAction(true));
        $actions->register(new LabelAction(false));
    }

    /**
     * `products()->label('sale')` — the products with any of these labels, by code. A code nobody
     * has matches no product rather than all of them, as a category slug nobody has does. A label
     * out of the filter still selects: a template picking the newsletter's products is exactly
     * what a service label is for.
     */
    private function registerQuery(): void
    {
        ProductQuery::macro('label', function (string ...$codes): ProductQuery {
            /** @var ProductQuery $this */
            $codes = array_values(array_filter(array_map('trim', $codes), static fn (string $code): bool => $code !== ''));

            if ($codes === []) {
                return $this;
            }

            return $this->narrowedBy('label', static function (Builder $query) use ($codes): void {
                $query->whereIn($query->getModel()->qualifyColumn('id'), $query->getQuery()->newQuery()
                    ->from(Label::LINKS.' as linked')
                    ->join('catalog_labels as labels', 'labels.id', '=', 'linked.label_id')
                    ->whereIn('labels.code', $codes)
                    ->whereNull('labels.deleted_at')
                    ->select('linked.product_id'));
            });
        });
    }
}
