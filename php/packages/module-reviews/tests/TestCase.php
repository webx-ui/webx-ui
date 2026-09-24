<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Tests;

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
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Pages\PagesServiceProvider;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;
use WebxUi\Reviews\ReviewsServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The module and what it is used with: the library its photos come from, and pages to stand
     * a reviews block on — the second is not required by the package, it is how a site has it.
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
            ReviewsServiceProvider::class,
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
     * A review in English, and in Russian too when the test gives a text for it.
     *
     * @param  list<ReviewCategory>  $categories
     * @param  array<string, mixed>  $attributes
     */
    protected function review(string $name, ?string $ru = null, bool $published = true, array $categories = [], array $attributes = []): Review
    {
        $review = Review::query()->create([
            'name' => ['en' => $name],
            'text' => array_filter(['en' => "{$name} liked it.", 'ru' => $ru]),
            'published' => $published,
            ...$attributes,
        ]);

        if ($categories !== []) {
            $review->syncCategories(array_map(static fn (ReviewCategory $category): int => $category->id, $categories));
        }

        return $review->refresh();
    }

    protected function category(string $title, bool $visible = true): ReviewCategory
    {
        return ReviewCategory::query()->create(['title' => ['en' => $title], 'is_visible' => $visible]);
    }

    /** A picture in the library, the way an upload leaves one. */
    protected function picture(string $path = 'media/ab/cd/anna.jpg'): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => str_repeat('a', 32),
            'name' => 'Anna',
            'file_name' => basename($path),
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'width' => 400,
            'height' => 400,
        ]);
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['reviews.view', 'reviews.manage', 'reviews.categories.manage']): CmsUser
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
        return rtrim('/api/cms/reviews/'.$path, '/');
    }

    /** The block type this module offers, installed the way a site installs it. */
    protected function installBlock(): Block
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['reviews']])->assertSuccessful();

        return Block::query()->where('slug', 'reviews')->firstOrFail();
    }
}
