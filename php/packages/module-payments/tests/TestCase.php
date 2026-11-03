<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\PaymentsServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

abstract class TestCase extends Orchestra
{
    use MigratesOnce;

    /** A key of «gateway A»: long enough to be looked for, fake, and in every encoding below. */
    protected const string SECRET = 'fake-secret-key/0001+abc=';

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing here may reach a gateway: every request in these tests is faked.
        Http::preventStrayRequests();
    }

    /**
     * The module and what it requires — the orders, and through them the cart.
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
            PaymentsServiceProvider::class,
        ];
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     */
    protected function secrets(array $headers = [], array $fields = []): ExchangeSecrets
    {
        return new ExchangeSecrets(Credentials::of(['secret_key' => self::SECRET, 'shop_id' => 'shop-a']), $headers, $fields);
    }
}
