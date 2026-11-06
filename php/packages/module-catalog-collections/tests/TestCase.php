<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Catalog\CatalogServiceProvider;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Rendering\ProductQuery;
use WebxUi\CatalogCollections\CatalogCollectionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The collections on the catalogue alone: nothing in the model asks for another satellite.
 */
abstract class TestCase extends Orchestra
{
    use MigratesOnce;

    /**
     * Macros are static and outlive the test that booted a satellite: `inStock` left by one would
     * ask for a table this schema does not have. Before the boot, so a case with the satellite
     * gets its macro back.
     */
    protected function setUp(): void
    {
        ProductQuery::flushMacros();

        parent::setUp();
    }

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
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
            CatalogServiceProvider::class,
            CatalogCollectionsServiceProvider::class,
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
        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'ru'],
        ]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function product(string $name, array $attributes = []): Product
    {
        $category = new Category(['name' => 'Parts', 'slug' => 'parts-'.uniqid(), 'is_published' => true]);
        $category->saveAsRoot();

        return Product::query()->create([
            'name' => $name,
            'category_id' => $category->id,
            'is_published' => true,
            'price' => 10,
            ...$attributes,
        ])->refresh();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function manager(array $permissions = ['catalog.view', 'catalog.manage']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Editor '.$count,
            'email' => "collections-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "collections-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    protected function api(string $path = ''): string
    {
        return rtrim('/api/cms/catalog/collections/'.$path, '/');
    }
}
