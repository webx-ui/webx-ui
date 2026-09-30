<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Bulk\BulkActions;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Search\SearchContributors;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertiesDocument;
use WebxUi\CatalogProperties\Catalog\PropertiesPart;
use WebxUi\CatalogProperties\Catalog\PropertyActions;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Facets\PropertySource;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\Localization\Locales;

/**
 * The properties of products (`WEBX_UI_CATALOG_PROPERTIES.md`): a satellite of the catalogue whose
 * facets live in the database. What it gives the core is what every satellite gives — the part of
 * the product form, the document, the bulk actions — plus a source of facets the core asks lazily,
 * which also says which of them belong on a page, and a share of the search.
 *
 * The section of the panel, the storefront parts and the agent's tools come with the next stages
 * of the series (§14): this provider is the data and the API.
 */
class CatalogPropertiesServiceProvider extends ServiceProvider
{
    /** Where the properties answer under the panel's API. */
    public const SOURCE = 'catalog/properties';

    /** Where their groups do — the panel's shared category screens. */
    public const GROUPS = 'catalog/property-groups';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog-properties.php', 'webx-catalog-properties');

        $this->app->singleton(Properties::class);
        $this->app->singleton(PropertySets::class);
        $this->app->singleton(ProductValues::class);
        $this->app->singleton(PropertySource::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-properties');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerScreens();
        $this->registerHistory();
        $this->registerCatalog();
        $this->registerEvents();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-catalog-properties.php' => config_path('webx-catalog-properties.php'),
        ], 'webx-catalog-properties-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-properties'),
        ], 'webx-catalog-properties-lang');
    }

    /**
     * The property's editor, the group's, and the «Specifications» tab of the product form: one
     * field, `properties.values`, which the node draws by the set of the main category.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);
        $screens->register(Property::SCREEN, __DIR__.'/../resources/screens/property-form.json');
        $screens->register(PropertyGroup::SCREEN, __DIR__.'/../resources/screens/property-group-form.json');

        $screens->extend(Product::SCREEN, [[
            'op' => 'add',
            'target' => 'tabs',
            'position' => 'after:main',
            'node' => [
                'id' => 'properties-tab',
                'type' => 'wx-tab',
                'label' => 'trans::webx-catalog-properties::product.tab',
                'children' => [[
                    'id' => 'properties-values',
                    'type' => 'wx-catalog-product-properties',
                    'name' => PropertiesPart::KEY.'.values',
                    'props' => ['source' => self::SOURCE, 'sets' => 'catalog/property-sets'],
                ]],
            ],
        ]]);

        $this->app->make(CategorySources::class)->register(self::GROUPS, PropertyGroup::class, 'webx-catalog-properties::errors.group');
    }

    /**
     * A property, a group and a value each write the journal of their own; the values of a product
     * are lines of the product's save. Read behind any of the catalogue's permissions.
     */
    private function registerHistory(): void
    {
        $types = $this->app->make(HistoryTypes::class);
        $permission = ['catalog.view', 'catalog.manage', 'catalog.delete'];
        $fields = [];

        foreach (['title', 'code', 'type', 'group_id', ...Property::FLAGS, 'filter_mode', 'value_order', 'unit_prefix', 'unit_suffix', 'precision', 'toggle_slug', 'seo_pattern'] as $field) {
            $fields[$field] = 'webx-catalog-properties::property.'.$field;
        }

        $types->register(Property::TYPE, Property::class, $fields, $permission, 'catalog-properties', 'webx-catalog-properties::module.property');
        $types->register(PropertyGroup::TYPE, PropertyGroup::class, ['title' => 'webx-catalog-properties::group.title', 'is_visible' => 'webx-catalog-properties::group.is_visible'], $permission, 'catalog-properties', 'webx-catalog-properties::module.group');
        $types->register(PropertyValue::TYPE, PropertyValue::class, [
            'title' => 'webx-catalog-properties::value.title',
            'slug' => 'webx-catalog-properties::value.slug',
            'color' => 'webx-catalog-properties::value.color',
            'image_id' => 'webx-catalog-properties::value.image',
        ], $permission, 'catalog-properties', 'webx-catalog-properties::module.value');
    }

    private function registerCatalog(): void
    {
        $this->app->make(ProductParts::class)->register($this->app->make(PropertiesPart::class));
        $this->app->make(Documents::class)->register($this->app->make(PropertiesDocument::class));

        $source = $this->app->make(PropertySource::class);
        $this->app->make(Facets::class)->source($source);
        $this->app->make(SearchContributors::class)->register($source);

        $actions = $this->app->make(BulkActions::class);

        foreach ([PropertyActions::SET, PropertyActions::REMOVE, PropertyActions::CLEAR] as $key) {
            $actions->register(new PropertyActions(
                $key,
                $this->app->make(Properties::class),
                $this->app->make(PropertySets::class),
                $this->app->make(ProductValues::class),
                $this->app->make(Locales::class),
            ));
        }
    }

    private function registerEvents(): void
    {
        $events = $this->app->make(Dispatcher::class);

        // A branch moved inherits another branch's properties: every set is stale, and the
        // products of the branch show and are found by other ones (§3.2).
        $events->listen('eloquent.moved: '.Category::class, function (Category $category): void {
            $sets = $this->app->make(PropertySets::class);
            $sets->forget();
            $sets->touchSubtree($category);
        });

        $events->listen(JobProcessing::class, function (): void {
            $this->app->make(Properties::class)->reset();
            $this->app->make(PropertySets::class)->reset();
        });
    }
}
