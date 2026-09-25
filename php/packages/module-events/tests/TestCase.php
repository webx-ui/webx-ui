<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

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
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Events\EventsServiceProvider;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Pages\PagesServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Services\Models\Service;
use WebxUi\Services\ServicesServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The module and what it is used with on a real site: the library its photos come from, the
     * services it is related to, pages to stand at the prefix, previews — none of the last three
     * is required by the package, which is what {@see WithoutServicesTest} is about.
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
            EventsServiceProvider::class,
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

        // Under a prefix: `DynamicComponent` remembers what it resolved in static properties
        // shared by every test in the process (CLAUDE.md §4).
        Blade::anonymousComponentPath(__DIR__.'/Fixtures/views', 'site');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    /**
     * An event, published unless the test says otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function event(string $slug, mixed $startsAt = null, bool $published = true, array $attributes = []): Event
    {
        $event = new Event([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'starts_at' => $startsAt,
            ...$attributes,
        ]);
        $event->save();

        if ($published) {
            $event->publish();
        }

        return $event->refresh();
    }

    protected function category(string $slug, bool $visible = true): EventCategory
    {
        return EventCategory::query()->create([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_visible' => $visible,
        ]);
    }

    protected function service(string $slug, bool $published = true): Service
    {
        $service = new Service(['title' => ucfirst($slug), 'slug' => $slug]);
        $service->save();

        if ($published) {
            $service->publish();
        }

        return $service->refresh();
    }

    /** A picture in the library, the way an upload leaves one. */
    protected function picture(string $path = 'media/ab/cd/class.jpg'): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => str_repeat('a', 32),
            'name' => basename($path, '.jpg'),
            'file_name' => basename($path),
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
        ]);
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['events.view', 'events.manage', 'events.categories.manage', 'services.view']): CmsUser
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
        return rtrim('/api/cms/events/'.$path, '/');
    }

    /** The languages the site is published in, for a test about translated values. */
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
     * The schema.org block of this type on the page.
     *
     * @return array<string, mixed>
     */
    protected function jsonLd(string $page, string $type): array
    {
        foreach ($this->jsonLds($page) as $block) {
            if (($block['@type'] ?? null) === $type) {
                return $block;
            }
        }

        $this->fail("No {$type} on the page.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function jsonLds(string $page): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $page, $matches);

        return array_values(array_map(static fn (string $json): array => (array) json_decode($json, true), $matches[1]));
    }
}
