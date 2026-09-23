<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

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
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;
use WebxUi\Services\ServicesServiceProvider;
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
            // The panel's administrators: the history of a service names who published it.
            AuthServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
            MediaServiceProvider::class,
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            ServicesServiceProvider::class,
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

        // Under a prefix, for the reason the blog's tests give: `DynamicComponent` remembers what
        // it resolved in static properties shared by every test in the process.
        Blade::anonymousComponentPath(__DIR__.'/Fixtures/views', 'site');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    /** A service, published unless the test says otherwise. */
    protected function service(string $slug, bool $published = true): Service
    {
        $service = new Service(['title' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug]);
        $service->save();

        if ($published) {
            $service->publish();
        }

        return $service->refresh();
    }

    protected function category(string $slug, bool $visible = true): ServiceCategory
    {
        return ServiceCategory::query()->create([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_visible' => $visible,
        ]);
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['services.view', 'services.manage', 'services.categories.manage']): CmsUser
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
        return rtrim('/api/cms/services/'.$path, '/');
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
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $page, $matches);

        foreach ($matches[1] as $json) {
            $block = (array) json_decode($json, true);

            if (($block['@type'] ?? null) === $type) {
                return $block;
            }
        }

        $this->fail("No {$type} on the page.");
    }
}
