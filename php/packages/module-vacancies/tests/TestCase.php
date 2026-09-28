<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

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
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Models\Form;
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Pages\PagesServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;
use WebxUi\Vacancies\VacanciesServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The module and what it is used with on a real site: the forms it points at, pages to stand
     * at the prefix, previews — none of which the package requires, which is what
     * {@see WithoutInboxTest} is about. The library comes with pages and blocks, not with this.
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
            InboxServiceProvider::class,
            VacanciesServiceProvider::class,
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
        $app['config']->set('filesystems.disks.inbox', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/inbox'),
        ]);
        $app['config']->set('webx-inbox.disk', 'inbox');
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
     * A vacancy, published unless the test says otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function vacancy(string $slug, bool $published = true, array $attributes = []): Vacancy
    {
        $vacancy = new Vacancy([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            ...$attributes,
        ]);
        $vacancy->save();

        if ($published) {
            $vacancy->publish();
        }

        return $vacancy->refresh();
    }

    protected function category(string $slug, bool $visible = true): VacancyCategory
    {
        return VacancyCategory::query()->create([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_visible' => $visible,
        ]);
    }

    protected function form(string $slug = 'job-application', bool $enabled = true): Form
    {
        return Form::query()->create([
            'slug' => $slug,
            'title' => ['en' => ucfirst(str_replace('-', ' ', $slug))],
            'is_enabled' => $enabled,
            'options' => [],
        ]);
    }

    /** The organisation `JobPosting` hires for — without it there is no markup at all. */
    protected function organisation(string $name = 'Acme'): void
    {
        $this->app->make(Settings::class)->save(['seo.org-name' => $name]);
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['vacancies.view', 'vacancies.manage', 'vacancies.categories.manage']): CmsUser
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
        return rtrim('/api/cms/vacancies/'.$path, '/');
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
     * The schema.org block of this type on the page, or null.
     *
     * @return array<string, mixed>|null
     */
    protected function jsonLd(string $page, string $type): ?array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $page, $matches);

        foreach ($matches[1] as $json) {
            $block = (array) json_decode($json, true);

            if (($block['@type'] ?? null) === $type) {
                return $block;
            }
        }

        return null;
    }
}
