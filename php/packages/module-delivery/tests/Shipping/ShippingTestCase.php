<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Shipping;

use Illuminate\Foundation\Application;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Cache\CacheServiceProvider;
use WebxUi\Cart\CartService;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Delivery\CartDelivery;
use WebxUi\Delivery\Checkout\DeliveryCheckoutSlot;
use WebxUi\Delivery\DeliveryServiceProvider;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Delivery\Testing\FakeParcels;
use WebxUi\Delivery\Tests\StepTestCase;
use WebxUi\Geography\GeographyServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Orders\PlaceOrder;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The shop of the step tests with the orders installed and «carrier A» (§18), whose method types
 * make waybills and track parcels through {@see FakeParcels}: an order is placed the way the
 * checkout places it — the slot's snapshot as its block `delivery`.
 */
abstract class ShippingTestCase extends StepTestCase
{
    protected FakeParcels $parcels;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            CacheServiceProvider::class,
            CompositionsServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
            GeographyServiceProvider::class,
            CartServiceProvider::class,
            OrdersServiceProvider::class,
            DeliveryServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(FakeParcels::class);
        $this->parcels = $this->app->make(FakeParcels::class);
        $this->carrierA(carrier: new FakeCarrier(localities: 0, points: 0));
    }

    /**
     * An order of the test's cart with this choice of delivery, placed as the checkout places it.
     *
     * @param  array<string, mixed>  $choice  columns of `delivery_selections`
     */
    protected function placeWith(array $choice): Order
    {
        // A cart a time: the one placed is the order's now.
        $this->basket = $this->app->make(CartService::class)->add(null, [['type' => 'test.goods', 'ref' => 1]], 'en')->cart;
        DeliverySelection::query()->updateOrCreate(['cart_id' => $this->basket->id], ['country_code' => 'XX', 'locality_id' => $this->townA->id, ...$choice]);
        $this->app->make(CartDelivery::class)->forget();

        $state = $this->app->make(Calculator::class)->calculate($this->basket, 'en', Purpose::Checkout);
        $block = $this->app->make(DeliveryCheckoutSlot::class)->snapshot(new CheckoutContext($this->basket, $state, null, null, 'en', 'EUR'));

        return $this->app->make(Orders::class)->place(new PlaceOrder(
            cart: $this->basket,
            locale: 'en',
            customer: new Contact('Ann', 'Example', '+800 1234 5678', 'ann@example.test'),
            blocks: ['delivery' => $block],
        ));
    }

    /** An order to a branch of carrier A: «by carrier rates» unless the method has a price. */
    protected function branchOrder(?int $price = null): Order
    {
        $method = DeliveryMethod::query()->where('key', 'carrier-a-branch')->firstOrFail();
        $method->forceFill(['price' => $price])->save();
        $point = DeliveryPoint::query()->where('carrier', 'carrier-a')->orderBy('id')->firstOrFail();

        return $this->placeWith(['method_id' => $method->id, 'point_id' => $point->id]);
    }

    /** @param  list<string>  $permissions */
    protected function signIn(array $permissions): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => "Manager {$count}",
            'email' => "manager-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "manager-{$count}", 'name' => 'Manager', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());
        $this->actingAs($user, 'cms');

        return $user;
    }
}
