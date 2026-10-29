<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Orders\Checkout\CheckoutAntispam;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Settings\Settings;

/**
 * «Buy in one click» of the orders on the product page (§29 of the orders' spec): beside the
 * cart's button while the setting is on, an order of this product from it, and the same options
 * for both buttons. Without the orders — {@see WithoutOrdersTest}.
 */
final class QuickButtonTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), CompositionsServiceProvider::class, CartServiceProvider::class, OrdersServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-catalog.price.currency', 'EUR');
    }

    #[Test]
    public function the_button_stands_beside_the_carts_only_while_one_click_is_on(): void
    {
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);

        $this->get($product->url())
            ->assertOk()
            ->assertSee('webx-cart-add', false)
            ->assertDontSee('webx-orders-quick', false);

        $this->app->make(Settings::class)->save(['orders.checkout.quick.enabled' => 'on']);

        $this->get($product->url())
            ->assertOk()
            ->assertSeeInOrder(['webx-cart-add', 'webx-orders-quick__button webx-catalog-product__action webx-catalog-product__quick'], false)
            ->assertSee('data-webx-dialog="webx-quick-catalog-product-'.$product->id.'"', false)
            ->assertSee('<input type="hidden" name="type" value="catalog.product">', false)
            ->assertSee('<input type="hidden" name="ref" value="'.$product->id.'">', false);
    }

    #[Test]
    public function a_product_that_cannot_be_bought_has_no_button(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.quick.enabled' => 'on']);
        $product = $this->product('Item A', $this->category('tools'));

        $this->get($product->url())
            ->assertOk()
            ->assertSee(__('webx-catalog::storefront.ask-price'))
            ->assertDontSee('webx-orders-quick', false);
    }

    #[Test]
    public function the_dialog_places_an_order_of_the_product(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.quick.enabled' => 'on']);
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);
        // The dialog's mark of time is the page's: an answer sooner than a person types is a robot's.
        $mark = $this->app->make(CheckoutAntispam::class)->timestamp();
        $this->travel(5)->seconds();

        $this->withHeaders(['Accept' => 'application/json'])->post('/checkout/quick', [
            'type' => 'catalog.product',
            'ref' => (string) $product->id,
            'qty' => '1',
            'phone' => '+800 1234 5678',
            CheckoutAntispam::HONEYPOT => '',
            CheckoutAntispam::TIMESTAMP => $mark,
        ])->assertOk();

        $order = Order::query()->with('items')->sole();
        $line = $order->items->sole();
        $this->assertSame('catalog.product', $line->type);
        $this->assertSame((int) $product->id, (int) $line->ref_id);
        $this->assertSame('+800 1234 5678', $order->customer_phone);
    }

    #[Test]
    public function both_buttons_get_the_same_options(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.quick.enabled' => 'on']);
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);
        View::composer('webx-catalog::product', static fn ($view) => $view->with('options', ['size' => 'L']));

        $this->get($product->url())
            ->assertOk()
            ->assertSee('name="items[0][options][size]" value="L"', false)
            ->assertSee('name="options[size]" value="L"', false);
    }

    #[Test]
    public function a_theme_takes_the_point_over(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.quick.enabled' => 'on']);
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);
        $views = sys_get_temp_dir().'/webx-catalog-quick-'.getmypid();
        @mkdir($views.'/parts/catalog/product', 0777, true);
        file_put_contents($views.'/parts/catalog/product/quick.blade.php', '');
        View::getFinder()->prependLocation($views);

        try {
            $this->get($product->url())
                ->assertOk()
                ->assertSee('webx-cart-add', false)
                ->assertDontSee('webx-orders-quick', false);
        } finally {
            @unlink($views.'/parts/catalog/product/quick.blade.php');
        }
    }
}
