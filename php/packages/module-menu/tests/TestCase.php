<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Menu\MenuServiceProvider;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;
use WebxUi\Menu\Tests\Fixtures\Thing;
use WebxUi\Menu\Tests\Fixtures\ThingServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;

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
            // The door an agent comes through; the section registers into the same registry
            // either way, and half of what this package offers is behind it.
            McpServiceProvider::class,
            MenuServiceProvider::class,
            ThingServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);

        // `vendor/bin/testbench` leaves a `.env` in the vendor directory with
        // `CACHE_STORE=database` in it, and after that every `remember` in this package is a
        // query against a database the tests have replaced. One line is cheaper than finding
        // that out from a failure in an unrelated file.
        $app['config']->set('cache.default', 'array');

        // sqlite ignores foreign keys until it is asked to, so without this the cascade that
        // takes a menu's items with it does not exist in the tests at all — and the test that
        // says it does would pass on code that never wrote it.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);

        $app['config']->set('webx-menu.menus', [
            'header' => ['title' => 'Header', 'variants' => ['link', 'button']],
            'footer' => ['title' => 'Footer'],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('things', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->string('slug')->default('');
            $table->boolean('published')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['menu.view', 'menu.manage']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Editor',
            'email' => "editor-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'slug' => "editor-{$count}",
            'name' => 'Editor',
            'permissions' => $permissions,
        ]);

        $user->roles()->attach($role->getKey());

        return $user;
    }

    protected function api(string $path = ''): string
    {
        return rtrim('/api/cms/menus/'.ltrim($path, '/'), '/');
    }

    /** The languages the site is published in, for a test about translated menus. */
    protected function useLocales(string ...$codes): void
    {
        $this->app['config']->set(
            'webx-localization.locales',
            array_map(
                static fn (string $code, int $index): array => ['code' => $code, 'default' => $index === 0],
                $codes,
                array_keys($codes),
            ),
        );

        $this->app->make(Locales::class)->forget();
    }

    /**
     * Where the visitor is, for a test about highlighting.
     *
     * The request is replaced in the container rather than driven through a route: the URL
     * generator keeps its own, so the addresses the items resolve to stay what they were and
     * only the question "where am I standing" changes.
     */
    protected function standingOn(string $path): void
    {
        $this->app->instance('request', Request::create(url('/'.ltrim($path, '/'))));
    }

    protected function menu(string $key = 'header'): Menu
    {
        return Menu::query()->firstOrCreate(['key' => $key], ['title' => ['en' => ucfirst($key)]]);
    }

    /**
     * An item at the end of a menu, or inside another item.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function item(array $attributes, ?Menu $menu = null, ?MenuItem $parent = null): MenuItem
    {
        $menu ??= $this->menu();

        $item = new MenuItem([
            'menu_id' => $menu->getKey(),
            'target' => 'none',
            ...$attributes,
        ]);

        if ($parent instanceof MenuItem) {
            $item->appendTo($parent);
        } else {
            $item->saveAsRoot();
        }

        return $item->refresh();
    }

    /**
     * @param  array<string, string>|string  $title
     */
    protected function thing(string $slug, array|string $title = 'Thing', bool $published = true): Thing
    {
        return Thing::query()->create([
            'title' => is_array($title) ? $title : ['en' => $title],
            'slug' => $slug,
            'published' => $published,
        ]);
    }

    /**
     * The labels of a tree, top level only, in order — what most of these tests are about.
     *
     * @return list<string>
     */
    protected function labels(string $key = 'header', ?string $locale = null): array
    {
        $labels = [];

        foreach (menu($key, $locale) as $link) {
            $labels[] = $link->label;
        }

        return $labels;
    }
}
