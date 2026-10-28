<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Purchasables;

/**
 * The cart is a suggestion of the catalogue, not a requirement: without its provider nothing is
 * registered, and the product page keeps its own button.
 */
final class WithoutCartTest extends TestCase
{
    #[Test]
    public function the_catalogue_works_and_registers_nothing_without_the_cart(): void
    {
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);

        $this->get($product->url())
            ->assertOk()
            ->assertSee('data-product="'.$product->id.'"', false)
            ->assertDontSee('webx-cart-add', false);

        $this->assertFalse($this->app->bound(Purchasables::class));
        $this->assertFalse($this->app->make('router')->has('webx-cart.items.store'));
    }
}
