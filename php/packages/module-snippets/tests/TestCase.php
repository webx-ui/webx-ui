<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\DynamicComponent;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionProperty;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\SnippetsServiceProvider;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

abstract class TestCase extends Orchestra
{
    use MigratesOnce;

    protected string $public;

    /**
     * The module the way a site has it: the panel to edit codes in, the blocks for the regions
     * of the layout, and theme-default's layout to print the places.
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
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
            SnippetsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $this->public = str_replace('\\', '/', sys_get_temp_dir()).'/webx-snippets-public-'.bin2hex(random_bytes(6));
        mkdir($this->public);
        $app->usePublicPath($this->public);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru']]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-themes.theme', 'webx-ui/theme-default');
        $app['config']->set('view.paths', [self::fixture('site')]);

        $app->resolving(ThemeLocator::class, static function (ThemeLocator $locator): void {
            $locator->register('webx-ui/theme-default', dirname(__DIR__, 2).'/theme-default');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        // A test that reaches the network waits on it; whatever it calls is faked.
        Http::preventStrayRequests();

        self::forgetDynamicComponents();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->public);
        self::forgetDynamicComponents();

        parent::tearDown();
    }

    /**
     * theme-default prints the places with `<x-dynamic-component>`, which keeps the compiler and
     * what it resolved in static properties: an earlier test's application would answer for this one.
     */
    private static function forgetDynamicComponents(): void
    {
        (new ReflectionProperty(DynamicComponent::class, 'compiler'))->setValue(null, null);
        (new ReflectionProperty(DynamicComponent::class, 'componentClasses'))->setValue(null, []);
    }

    /**
     * A code as the panel saves it: enabled, printed on dev and stage too unless the test says
     * otherwise — the tests run in `testing`, which is not production.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function snippet(string $place, string $code, array $attributes = []): Snippet
    {
        static $count = 0;
        $count++;

        return Snippet::query()->create([
            'name' => "Snippet {$count}",
            'place' => $place,
            'code' => $code,
            'enabled' => true,
            'on_stage' => true,
            ...$attributes,
        ]);
    }

    /**
     * Somebody the panel lets in: a super administrator, or an editor with these permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function admin(bool $super = true, array $permissions = []): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => "Admin {$count}",
            'email' => "admin-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => $super,
            'is_active' => true,
        ]);

        if (! $super) {
            $role = Role::query()->create(['slug' => "role-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
            $user->roles()->attach($role->getKey());
        }

        return $user;
    }

    /** A request to the site at this path, for rendering a place outside a route. */
    protected function at(string $path, string $locale = 'en'): void
    {
        $this->app->instance('request', Request::create($path));
        $this->app->make(Locales::class)->use($locale);
    }

    protected static function fixture(string $path): string
    {
        return str_replace('\\', '/', __DIR__).'/Fixtures/'.$path;
    }
}
