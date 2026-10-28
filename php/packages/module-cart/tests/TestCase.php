<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Cart\PurchasableLine;
use WebxUi\Cart\Purchasables;
use WebxUi\Cart\Tests\Fakes\FakeConfigurations;
use WebxUi\Cart\Tests\Fakes\FakeProducts;
use WebxUi\Localization\LocalizationServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected FakeProducts $products;

    protected FakeConfigurations $configurations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = new FakeProducts([
            1 => self::product('Item A', 4999),
            2 => self::product('Item B', 15000),
            3 => self::product('Item C', 48000),
        ]);
        $this->configurations = new FakeConfigurations;

        $purchasables = $this->app->make(Purchasables::class);
        $purchasables->register('test.product', $this->products);
        $purchasables->register('test.configuration', $this->configurations);
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            CartServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'pl']]);
        $app['config']->set('webx-cart.currency', 'EUR');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    /** @param  array<string, string>|null  $names */
    protected static function product(string $name, int $price, string $currency = 'EUR', ?int $maxQty = null, bool $available = true, ?array $names = null): PurchasableLine
    {
        return new PurchasableLine(
            name: $name,
            sku: strtoupper(substr(str_replace(' ', '-', $name), 0, 8)),
            price: $price,
            currency: $currency,
            url: '/'.strtolower(str_replace(' ', '-', $name)),
            maxQty: $maxQty,
            available: $available,
            names: $names,
        );
    }

    /** The visitor's request carries this token from now on. */
    protected function withCartCookie(string $token): void
    {
        $this->app['request']->cookies->set($this->app->make(CartCookie::class)->name(), $token);
    }
}
