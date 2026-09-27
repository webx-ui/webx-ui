<?php

declare(strict_types=1);

namespace WebxUi\Team\Tests;

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
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Services\Models\Service;
use WebxUi\Services\ServicesServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Team\Models\Member;
use WebxUi\Team\TeamServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The module and what it is used with: the library its photos come from, pages to stand a
     * team block on and services to link people to — neither of the last two is required by the
     * package, it is how a site has them.
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
            TeamServiceProvider::class,
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
     * A person in English, with a Russian text too when the test gives one.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function member(string $name, ?string $ru = null, bool $published = true, array $attributes = []): Member
    {
        $member = Member::query()->create([
            'name' => ['en' => $name],
            'text' => array_filter(['en' => "{$name} works here.", 'ru' => $ru]),
            'published' => $published,
            ...$attributes,
        ]);

        return $member->refresh();
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
    protected function editor(array $permissions = ['team.view', 'team.manage', 'services.view']): CmsUser
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
        return rtrim('/api/cms/team/'.$path, '/');
    }

    /** The block type this module offers, installed the way a site installs it. */
    protected function installBlock(): Block
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['team']])->assertSuccessful();

        return Block::query()->where('slug', 'team')->firstOrFail();
    }
}
