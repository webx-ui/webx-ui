<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Catalog\CatalogServiceProvider;
use WebxUi\Catalog\Engine\CatalogEngines;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLandings\CatalogLandingsServiceProvider;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogLandings\Tests\Fixtures\QueueingEngine;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The landings with brands and properties as the facets of their sets: the satellites a real
 * site has, so the sets are made of values that can be merged and deleted.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            MediaServiceProvider::class,
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            CatalogServiceProvider::class,
            BrandsServiceProvider::class,
            CatalogPropertiesServiceProvider::class,
            CatalogLandingsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);

        // The database answers every search, and the engine still asks to be told what changed —
        // so a test sees the counts and can run the index that marks the landings.
        $app['config']->set('webx-catalog.engine', 'queueing');
        $app->afterResolving(CatalogEngines::class, static function (CatalogEngines $engines): void {
            $engines->register('queueing', QueueingEngine::class);
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function category(string $slug, ?Category $parent = null): Category
    {
        $category = new Category(['name' => ucfirst($slug), 'slug' => $slug, 'is_published' => true]);
        $parent === null ? $category->saveAsRoot() : $category->appendTo($parent->refresh());

        return $category->refresh();
    }

    protected function brand(string $title): Brand
    {
        return Brand::query()->create(['title' => $title, 'slug' => strtolower($title), 'is_visible' => true])->refresh();
    }

    /**
     * A published product in a category, of this brand when one is given.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function product(string $name, Category $category, ?Brand $brand = null, array $attributes = []): Product
    {
        $product = Product::query()->create([
            'name' => $name,
            'category_id' => $category->id,
            'is_published' => true,
            'price' => 10,
            ...$attributes,
        ]);

        if ($brand !== null) {
            DB::table(Brand::LINKS)->insert(['product_id' => $product->id, 'brand_id' => $brand->id]);
        }

        return $product->refresh();
    }

    /**
     * A published landing, its set given as facet key → ids or a range.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $attributes
     */
    protected function landing(string $slug, ?Category $base, array $filters, array $attributes = []): Landing
    {
        return Landing::query()->create([
            'category_id' => $base?->id,
            'filters' => $filters,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_published' => true,
            ...$attributes,
        ])->refresh();
    }

    /**
     * @param  list<Brand>  $brands
     * @return array{values: list<string>}
     */
    protected function brands(array $brands): array
    {
        return ['values' => array_map(static fn (Brand $brand): string => (string) $brand->id, $brands)];
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['catalog.view', 'catalog.manage', 'catalog.delete']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Editor '.$count,
            'email' => "landings-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "landings-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    protected function api(string $path = ''): string
    {
        return rtrim('/api/cms/catalog/landings/'.$path, '/');
    }
}
