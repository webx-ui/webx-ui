<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Catalog\Orders\QuickButton;

/**
 * The orders are a suggestion of the catalogue, not a requirement: with the cart and without the
 * orders' module the product page compiles and prints the cart's button alone — the tag of
 * «Buy in one click» is a dynamic component, and the registry, not the class, decides.
 */
final class WithoutOrdersTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), CartServiceProvider::class];
    }

    #[Test]
    public function the_product_page_has_the_carts_button_and_no_one_click(): void
    {
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);

        $this->assertFalse($this->app->make(ModuleRegistry::class)->has(QuickButton::MODULE));
        $this->assertFalse(QuickButton::shown());

        $this->get($product->url())
            ->assertOk()
            ->assertSee('webx-cart-add', false)
            ->assertDontSee('webx-orders-quick', false)
            ->assertDontSee('webx-orders::', false);
    }
}
