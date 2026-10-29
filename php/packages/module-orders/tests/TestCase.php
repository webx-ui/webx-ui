<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

abstract class TestCase extends Orchestra
{
    use MigratesOnce;

    /**
     * The module and what it requires — the cart, the widgets, the compositions — and none of
     * what it only suggests (spec §1.1).
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            CompositionsServiceProvider::class,
            AdminServiceProvider::class,
            WidgetsServiceProvider::class,
            CartServiceProvider::class,
            OrdersServiceProvider::class,
        ];
    }

    /**
     * Three languages of the site: two the package speaks, and one it does not — whose statuses
     * read the fallback's words until somebody writes their own.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru'], ['code' => 'kk']]);
        $app['config']->set('webx-localization.cache.enabled', false);
        // sqlite ignores foreign keys unless asked, and a cascade that does not exist would pass.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }
}
