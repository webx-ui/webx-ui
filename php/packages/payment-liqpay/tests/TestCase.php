<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Payment\LiqPay\PaymentLiqPayServiceProvider;
use WebxUi\Payments\PaymentsServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/** Nothing here reaches LiqPay: a stray request fails the test. */
abstract class TestCase extends Orchestra
{
    use MigratesOnce;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * The satellite, the base, and what the base requires.
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
            PaymentLiqPayServiceProvider::class,
        ];
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'uk']]);
        $app['config']->set('webx-localization.cache.enabled', false);
    }
}
