<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Tests;

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
use WebxUi\CatalogBrands\Tests\Fixtures\QueueingEngine;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

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
        // so a test sees both the counts and the queue.
        $app['config']->set('webx-catalog.engine', 'queueing');
        $app->afterResolving(CatalogEngines::class, static function (CatalogEngines $engines): void {
            $engines->register('queueing', QueueingEngine::class);
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function category(string $slug): Category
    {
        $category = new Category(['name' => ucfirst($slug), 'slug' => $slug, 'is_published' => true]);
        $category->saveAsRoot();

        return $category->refresh();
    }

    /**
     * A published brand whose slug is its name in lowercase, unless the attributes say otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function brand(string $title, array $attributes = []): Brand
    {
        return Brand::query()->create([
            'title' => $title,
            'slug' => strtolower($title),
            'is_visible' => true,
            ...$attributes,
        ])->refresh();
    }

    /**
     * A published product with a price in a category, of this brand when one is given.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function product(string $name, ?Brand $brand = null, ?Category $category = null, array $attributes = []): Product
    {
        $category ??= Category::query()->where('slug->en', 'laptops')->first() ?? $this->category('laptops');

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
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['catalog.view', 'catalog.manage', 'catalog.delete']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Editor '.$count,
            'email' => "brands-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "brands-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    protected function api(string $path = ''): string
    {
        return rtrim('/api/cms/catalog/'.$path, '/');
    }
}
