<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Catalog\CatalogServiceProvider;
use WebxUi\CatalogFeeds\CatalogFeedsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Templates\TemplatesServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

abstract class TestCase extends Orchestra
{
    use MigratesOnce;

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
            TemplatesServiceProvider::class,
            CatalogServiceProvider::class,
            CatalogFeedsServiceProvider::class,
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
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'de']]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
        $app['config']->set('webx-catalog.price.currency', 'EUR');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['catalog.view', 'catalog.manage']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Editor '.$count,
            'email' => "feeds-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "feeds-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    protected function api(string $path = ''): string
    {
        return rtrim('/api/cms/catalog/feeds/'.$path, '/');
    }

    /**
     * What a form sends for a small feed.
     *
     * @return array<string, mixed>
     */
    protected function fields(): array
    {
        return ['name' => 'Spare parts', 'format' => 'xml', 'item' => '<item>{{name}}</item>'];
    }
}
