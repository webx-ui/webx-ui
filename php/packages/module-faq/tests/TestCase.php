<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
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
use WebxUi\Faq\FaqServiceProvider;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Pages\PagesServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The module and what it is used with: pages to stand a FAQ block on, and the SEO module to
     * print its markup. Neither is required by the package — both are how a site has it.
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
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            PagesServiceProvider::class,
            FaqServiceProvider::class,
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
     * A question in both languages of the site unless the test says otherwise.
     *
     * @param  array<string, string>|null  $answer
     * @param  list<FaqCategory>  $categories
     */
    protected function question(string $en, ?string $ru = null, bool $published = true, ?array $answer = null, array $categories = []): Question
    {
        $question = Question::query()->create([
            'question' => array_filter(['en' => $en, 'ru' => $ru]),
            'answer' => $answer ?? array_filter(['en' => "<p>About {$en}.</p>", 'ru' => $ru === null ? null : "<p>Про {$ru}.</p>"]),
            'published' => $published,
        ]);

        if ($categories !== []) {
            $question->syncCategories(array_map(static fn (FaqCategory $category): int => $category->id, $categories));
        }

        return $question->refresh();
    }

    protected function category(string $title, bool $visible = true): FaqCategory
    {
        return FaqCategory::query()->create(['title' => ['en' => $title], 'is_visible' => $visible]);
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['faq.view', 'faq.manage', 'faq.categories.manage']): CmsUser
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
        return rtrim('/api/cms/faq/questions/'.$path, '/');
    }

    /** The block type this module offers, installed the way a site installs it. */
    protected function installBlock(): Block
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['faq']])->assertSuccessful();

        return Block::query()->where('slug', 'faq')->firstOrFail();
    }

    /** A fresh request, the way the next page of the site starts with nothing gathered. */
    protected function nextRequest(string $locale = 'en'): void
    {
        $this->app->instance('request', Request::create('/'));
        $this->app->make(Locales::class)->use($locale);
    }
}
