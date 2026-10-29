<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Foundation\Application;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\CartService;
use WebxUi\Cart\PurchasableLine;
use WebxUi\Cart\Purchasables;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Orders\PlaceOrder;
use WebxUi\Orders\Tests\Fakes\FakeProducts;

/** A cart of fake products in euros, and the order made of it. */
abstract class PlaceTestCase extends TestCase
{
    protected FakeProducts $products;

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = new FakeProducts([
            1 => self::product('Item A', 4999, names: ['en' => 'Item A', 'ru' => 'Товар A']),
            2 => self::product('Set B', 15000),
            3 => self::product('Part C', 700),
            4 => self::product('Gift D', 2500),
        ]);

        $this->app->make(Purchasables::class)->register('test.product', $this->products);
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-cart.currency', 'EUR');
    }

    /** @param  array<string, string>|null  $names */
    protected static function product(string $name, int $price, ?int $maxQty = null, bool $available = true, ?array $names = null, string $currency = 'EUR', bool $shippable = true): PurchasableLine
    {
        return new PurchasableLine(
            name: $name,
            sku: strtoupper(str_replace(' ', '-', $name)),
            price: $price,
            currency: $currency,
            optionsLabel: null,
            image: '/media/'.strtolower(str_replace(' ', '-', $name)).'.webp',
            maxQty: $maxQty,
            available: $available,
            names: $names,
            shippable: $shippable,
        );
    }

    /** @param  list<array<string, mixed>>  $items */
    protected function cart(array $items = [['type' => 'test.product', 'ref' => 1]]): AddResult
    {
        return $this->app->make(CartService::class)->add(null, $items, 'en');
    }

    /** @param  array<string, mixed>  $with  named arguments of PlaceOrder over the defaults */
    protected function place(AddResult $cart, array $with = []): Order
    {
        return $this->app->make(Orders::class)->place(new PlaceOrder(...[
            'cart' => $cart->cart,
            'locale' => 'en',
            'customer' => new Contact('Alex', 'Doe', '+1 555 010-0000', 'Alex@Example.test'),
            ...$with,
        ]));
    }
}
