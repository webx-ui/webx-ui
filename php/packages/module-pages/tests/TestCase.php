<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

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
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\PagesServiceProvider;
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
            // The door an agent comes through; the section offers its tools to it (§13).
            McpServiceProvider::class,
            BlocksServiceProvider::class,
            // A hard dependency since the SEO card arrived on the editor (§12): the tab is a
            // patch from that package, and `@webxSeo` in the page view is its directive.
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            PagesServiceProvider::class,
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
        // The compiled rule list would otherwise outlive a test that writes a rule and then
        // asks for the page it is about.
        $app['config']->set('webx-seo.cache.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // A layout of the site's own, the way a real one is reached. Registered for every test
        // rather than only for the one that uses it, and under a prefix rather than as a bare
        // `<x-layout>`: `DynamicComponent` keeps its tag compiler in one static property and the
        // names it has already resolved in another, so the first page rendered in the process
        // decides what `<x-dynamic-component>` can find for every test after it — in this package
        // and in the next one. A prefix is what makes that survivable: the view namespace is
        // hashed from the prefix and not from the path, so two packages registering `site` both
        // resolve, each to its own directory.
        Blade::anonymousComponentPath(__DIR__.'/Fixtures/views', 'site');
    }

    protected function tearDown(): void
    {
        // One compiled file per block version, and every test starts its versions at 1.
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['pages.view', 'pages.manage']): CmsUser
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
        return rtrim('/api/cms/pages/'.$path, '/');
    }

    /** The languages the site is published in, for a test about translated addresses. */
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

    /** A page under the home page unless another parent is named. */
    protected function page(string $slug, ?Page $parent = null, bool $published = true): Page
    {
        $page = new Page(['title' => ucfirst($slug), 'slug' => $slug]);
        $page->appendTo($parent ?? $this->home());

        if ($published) {
            $page->publish();
        }

        return $page->refresh();
    }

    protected function home(): Page
    {
        $home = Page::home();

        $this->assertInstanceOf(Page::class, $home, 'The migration should have created the home page.');

        return $home;
    }

    /**
     * A block type with one published version, the way the panel would have made it.
     */
    protected function blockType(string $slug, string $template): Block
    {
        $block = Block::query()->create(['slug' => $slug, 'title' => ucfirst($slug)]);

        $block->saveVersion(['template' => $template, 'schema' => [['id' => 'text', 'type' => 'wx-input']]]);
        $block->publish();

        return $block->refresh();
    }
}
