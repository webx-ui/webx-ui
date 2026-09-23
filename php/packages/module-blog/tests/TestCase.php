<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
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
use WebxUi\Blog\BlogServiceProvider;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
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
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
            MediaServiceProvider::class,
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            BlogServiceProvider::class,
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
        // asks for the page it is about — which is most of the SEO tests in here.
        $app['config']->set('webx-seo.cache.enabled', false);
        // sqlite ignores foreign keys unless it is asked to, and half the promises of the
        // migrations live in them: without this a cascade that does not exist still passes.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
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
        Carbon::setTestNow();

        // One compiled file per block version, and every test starts its versions at 1.
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    /**
     * An article, published unless the test says otherwise.
     *
     * `$at` is what makes a test about scheduling worth writing: an article dated in the future
     * is published as far as `HasDraft` is concerned and is not on the site (§7), and a test
     * that only ever uses `now()` is green against code that never checks.
     */
    protected function article(string $slug, bool $published = true, ?Carbon $at = null): Article
    {
        $article = new Article(['title' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug]);
        $article->save();

        if ($published || $at !== null) {
            $article->publish(at: $at);
        }

        return $article->refresh();
    }

    protected function rubric(string $slug, bool $visible = true): Rubric
    {
        return Rubric::query()->create([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_visible' => $visible,
        ]);
    }

    protected function tag(string $slug, bool $noindex = true): Tag
    {
        return Tag::query()->create([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'noindex' => $noindex,
        ]);
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['blog.articles.view', 'blog.articles.manage']): CmsUser
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
        return rtrim('/api/cms/blog/articles/'.$path, '/');
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

    /** A block type with one published version, the way the panel would have made it. */
    protected function blockType(string $slug, string $template): Block
    {
        $block = Block::query()->create(['slug' => $slug, 'title' => ucfirst($slug)]);

        $block->saveVersion(['template' => $template, 'schema' => [['id' => 'text', 'type' => 'wx-input']]]);
        $block->publish();

        return $block->refresh();
    }
}
