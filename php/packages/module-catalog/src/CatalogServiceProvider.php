<?php

declare(strict_types=1);

namespace WebxUi\Catalog;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\Catalog\Panel\CategoryFieldType;
use WebxUi\Catalog\Panel\FacetsFieldType;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Routing\CatalogMisses;
use WebxUi\Catalog\Routing\CategoryHandler;
use WebxUi\Catalog\Routing\ProductHandler;
use WebxUi\Catalog\Seo\UnavailableSource;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\Formatters\SlugId;
use WebxUi\Routing\Misses;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Seo\Rendering\SeoSources;

/**
 * The core of the catalogue: products, a tree of categories, the gallery, and the registries its
 * satellites plug into (§7). Most of what it does is registrations in somebody else's registry —
 * two kinds of address, the answers to addresses nobody holds any more, two screens, two field
 * types, two kinds of journal entry and a section of the panel.
 */
class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog.php', 'webx-catalog');

        $this->app->singleton(ProductParts::class);
        $this->app->singleton(Catalog::class);
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

        $this->app->make(SeoSources::class)->register(new UnavailableSource);

        if (! $this->app->runningInConsole()) {
            return;
        }

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

        $units = array_values(array_map('strval', (array) $this->config()->get('webx-catalog.units', [])));
        $patch = [[
            'op' => 'set',
            'target' => 'unit',
            'props' => ['options' => array_map(
                static fn (string $unit): array => ['label' => 'trans::webx-catalog::units.'.$unit, 'value' => $unit],
                $units,
            )],
        ]];

        if (! (bool) $this->config()->get('webx-catalog.price.enabled', true)) {
            $patch[] = ['op' => 'remove', 'target' => 'prices'];
        }

        if (! (bool) $this->config()->get('webx-catalog.fields.barcode', true)) {
            $patch[] = ['op' => 'remove', 'target' => 'barcode-col'];
        }

        $screens->extend(Product::SCREEN, $patch);

        $fields = $this->app->make(FieldTypes::class);
        $fields->register('wx-catalog-category', new CategoryFieldType);
        $fields->register('wx-catalog-facets', new FacetsFieldType);
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
     * that published `webx-admin.php` has its own copy of the list (CLAUDE.md §4).
     */
    private function registerPanel(): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $this->config()->get('webx-admin.groups', []);

        if (! array_key_exists(CatalogModule::GROUP, $groups)) {
            $this->config()->set('webx-admin.groups', [
                ...$groups,
                CatalogModule::GROUP => ['title' => 'webx-catalog::module.group', 'icon' => 'cart', 'order' => 300],
            ]);
        }

        $this->app->make(ModuleRegistry::class)->register($this->app->make(CatalogModule::class));
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
