<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

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
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\CatalogProperties\Tests\Fixtures\QueueingEngine;
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
            CatalogPropertiesServiceProvider::class,
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

    protected function category(string $slug, ?Category $parent = null): Category
    {
        $category = new Category(['name' => ucfirst($slug), 'slug' => $slug, 'is_published' => true]);
        $parent === null ? $category->saveAsRoot() : $category->appendTo($parent->refresh());

        return $category->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function property(string $title, string $type = Property::SELECT, array $attributes = []): Property
    {
        return Property::query()->create(['title' => $title, 'type' => $type, 'is_filterable' => $type !== Property::TEXT, ...$attributes])->refresh();
    }

    protected function value(Property $property, string $title, ?PropertyValue $parent = null): PropertyValue
    {
        $value = new PropertyValue(['property_id' => $property->id, 'title' => $title]);
        $parent === null ? $value->saveAsRoot() : $value->appendTo($parent->refresh());

        return $value->refresh();
    }

    /**
     * @param  list<Property>  $properties
     */
    protected function set(Category $category, array $properties): void
    {
        $this->app->make(PropertySets::class)->save($category, array_map(static fn (Property $property): int => (int) $property->id, $properties));
    }

    /**
     * A published product with a price in a category, holding these values as rows.
     *
     * @param  array<int, mixed>  $values  property id → value in its type's shape
     */
    protected function product(string $name, Category $category, array $values = []): Product
    {
        $product = Product::query()->create([
            'name' => $name,
            'category_id' => $category->id,
            'is_published' => true,
            'price' => 10,
        ]);

        foreach ($values as $property => $value) {
            foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $one) {
                DB::table('catalog_product_property_values')->insert([
                    'product_id' => $product->id,
                    'property_id' => $property,
                    'value_id' => $one instanceof PropertyValue ? $one->id : null,
                    'number' => is_float($one) || is_int($one) ? $one : null,
                    'flag' => $one === true ? true : null,
                    'text' => is_array($one) ? json_encode($one) : null,
                ]);
            }
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
            'email' => "properties-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "properties-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    protected function api(string $path = ''): string
    {
        return rtrim('/api/cms/catalog/'.$path, '/');
    }

    /** @return list<int> */
    protected function queued(): array
    {
        return DB::table('catalog_index_queue')->orderBy('product_id')->pluck('product_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    protected function clearQueue(): void
    {
        DB::table('catalog_index_queue')->delete();
    }
}
