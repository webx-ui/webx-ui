<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Pages\PagesServiceProvider;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Panel\OutletForm;
use WebxUi\Press\PressServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The module and what it is used with on a real site: the library its logos and PDFs come
     * from, and pages to stand at the prefix — the second is not required by the package, it is
     * how a site has it.
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
            PressServiceProvider::class,
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
     * An outlet with these articles, saved through the form — the door the panel uses.
     *
     * @param  list<array<string, mixed>>  $articles
     * @param  array<string, mixed>  $values
     */
    protected function outlet(string $title, array $articles = [], bool $published = true, array $values = []): Outlet
    {
        return $this->app->make(OutletForm::class)->save(new Outlet, [
            'title' => ['en' => $title],
            'slug' => ['en' => str($title)->slug()->toString()],
            'published' => $published,
            'articles' => $articles,
            ...$values,
        ]);
    }

    /**
     * A row of the articles repeater: in English, leading to an address, unless the test says.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function row(string $title, array $values = []): array
    {
        return [
            'title' => ['en' => $title],
            'url' => 'https://news.example/'.str($title)->slug(),
            ...$values,
        ];
    }

    /** A file in the library, the way an upload leaves one. */
    protected function file(string $path = 'media/ab/cd/scan.pdf', string $mime = 'application/pdf'): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => str_repeat('a', 32),
            'name' => basename($path),
            'file_name' => basename($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'mime' => $mime,
            'size' => 2048,
            'width' => str_starts_with($mime, 'image/') ? 400 : null,
            'height' => str_starts_with($mime, 'image/') ? 100 : null,
        ]);
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['press.view', 'press.manage']): CmsUser
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
        return rtrim('/api/cms/press/'.$path, '/');
    }

    /** @return list<string> */
    protected function titles(Outlet $outlet): array
    {
        return $outlet->articles()->get()->map(static fn (Article $article): string => (string) $article->getTranslation('title', 'en'))->all();
    }
}
