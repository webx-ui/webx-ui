<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\PagesServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Services\Models\Service;
use WebxUi\Services\ServicesServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;
use WebxUi\Tariffs\TariffsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The module and what it is used with: pages to stand a tariffs block on and services to link
     * tariffs to — neither is required by the package, it is how a site has them. The library
     * comes along because pages and services need it, not because a tariff does.
     *
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
            BlocksServiceProvider::class,
            MediaServiceProvider::class,
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            PagesServiceProvider::class,
            ServicesServiceProvider::class,
            TariffsServiceProvider::class,
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
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru']]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
        // sqlite ignores foreign keys unless asked, and a cascade that does not exist would pass.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Under a prefix, for the reason the blog's tests give: `DynamicComponent` remembers what
        // it resolved in static properties shared by every test in the process.
        Blade::anonymousComponentPath(__DIR__.'/Fixtures/views', 'site');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    /**
     * A tariff in English, published unless the test says otherwise.
     *
     * @param  list<TariffCategory>  $categories
     * @param  array<string, mixed>  $attributes
     */
    protected function tariff(string $name, bool $published = true, array $categories = [], array $attributes = []): Tariff
    {
        $tariff = Tariff::query()->create([
            'name' => ['en' => $name],
            'published' => $published,
            ...$attributes,
        ]);

        if ($categories !== []) {
            $tariff->syncCategories(array_map(static fn (TariffCategory $category): int => $category->id, $categories));
        }

        return $tariff->refresh();
    }

    protected function group(string $title, bool $visible = true): TariffCategory
    {
        return TariffCategory::query()->create(['title' => ['en' => $title], 'is_visible' => $visible]);
    }

    /** A published service at `/services/<slug>`. */
    protected function service(string $slug, bool $published = true): Service
    {
        $service = new Service(['title' => ucfirst($slug), 'slug' => $slug]);
        $service->save();

        if ($published) {
            $service->publish();
        }

        return $service->refresh();
    }

    /** A published page under the home page, at `/<slug>`. */
    protected function page(string $slug, bool $published = true): Page
    {
        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);

        $page = new Page(['title' => ucfirst($slug), 'slug' => $slug]);
        $page->appendTo($home);

        if ($published) {
            $page->publish();
        }

        return $page->refresh();
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['tariffs.view', 'tariffs.manage', 'tariffs.groups.manage', 'services.view', 'pages.view']): CmsUser
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

        $role = Role::query()->create(['slug' => "editor-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    protected function api(string|int $path = ''): string
    {
        return rtrim('/api/cms/tariffs/'.$path, '/');
    }

    /** The block type this module offers, installed the way a site installs it. */
    protected function installBlock(): Block
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['tariffs']])->assertSuccessful();

        return Block::query()->where('slug', 'tariffs')->firstOrFail();
    }

    /**
     * A link to a hand-written address, the way a `wx-link` field sends one.
     *
     * @return array<string, mixed>
     */
    protected static function url(string $url, bool $newTab = false): array
    {
        return ['target' => 'url', 'entity_type' => null, 'entity_id' => null, 'url' => $url, 'hash' => null, 'new_tab' => $newTab, 'rel' => []];
    }
}
