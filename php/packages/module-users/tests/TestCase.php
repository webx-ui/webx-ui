<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Users\Models\User;
use WebxUi\Users\UsersServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The module on a site with the panel's frame and the widgets' forms, and nothing of a shop —
 * which is how it must work. The pages print their bare document: no theme is installed.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected const string PASSWORD = 'correct-horse-battery';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The theme engine first: it wraps the translation loader, and the widgets stand on it.
        return [
            ThemeServiceProvider::class,
            LocalizationServiceProvider::class,
            CompositionsServiceProvider::class,
            AdminServiceProvider::class,
            WidgetsServiceProvider::class,
            UsersServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'pl']]);
        $app['config']->set('webx-localization.cache.enabled', false);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function visitor(array $attributes = []): User
    {
        static $count = 0;
        $count++;

        return User::query()->create([
            'email' => "anna-{$count}@example.test",
            'password' => self::PASSWORD,
            ...$attributes,
        ]);
    }
}
