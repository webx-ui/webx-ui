<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Banners\BannersServiceProvider;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Places;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\PagesServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The module and what it is used with: the library its pictures come from, and pages for a
     * button to point at — not required by the package, it is how a site has them.
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
            BannersServiceProvider::class,
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

    /**
     * A banner in a place, with a picture, turned on — in English unless the test says otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function banner(string $place = 'hero', ?string $title = 'Spring sale', array $attributes = []): Banner
    {
        $row = $this->app->make(Places::class)->row($place);
        $this->assertNotNull($row, "no place {$place}");

        $banner = new Banner([
            'image' => ['path' => 'media/ab/cd/wide.jpg'],
            'title' => $title === null ? null : ['en' => $title],
            'enabled' => true,
            ...$attributes,
        ]);
        $banner->moveToEndOf((int) $row->getKey());
        $banner->save();

        return $banner->refresh();
    }

    /** A file in the library, the way an upload leaves one. */
    protected function picture(string $path = 'media/ab/cd/wide.jpg', string $mime = 'image/jpeg'): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => md5($path),
            'name' => pathinfo($path, PATHINFO_FILENAME),
            'file_name' => basename($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'mime' => $mime,
            'size' => 2048,
            'width' => str_starts_with($mime, 'image/') ? 1920 : null,
            'height' => str_starts_with($mime, 'image/') ? 720 : null,
        ]);
    }

    /** A page under the home page, published unless asked otherwise. */
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
    protected function editor(array $permissions = ['banners.view', 'banners.manage']): CmsUser
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
        return rtrim('/api/cms/banners/'.$path, '/');
    }

    /**
     * A link to a hand-written address, as `wx-link` sends it.
     *
     * @return array<string, mixed>
     */
    protected static function url(string $url): array
    {
        return ['target' => 'url', 'url' => $url, 'entity_type' => null, 'entity_id' => null, 'hash' => null, 'new_tab' => false, 'rel' => []];
    }
}
