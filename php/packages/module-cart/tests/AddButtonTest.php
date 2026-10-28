<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartService;

/**
 * `<x-webx-cart::add>` (§9.3): a form with the CSRF token around the button, so it works without
 * JavaScript; the data attributes the widget's script reads; `is-in-cart` from the server.
 */
final class AddButtonTest extends TestCase
{
    #[Test]
    public function the_button_is_a_form_that_works_without_javascript(): void
    {
        $this->startSession();

        $html = Blade::render('<x-webx-cart::add type="test.product" :ref="2" class="buy">Buy</x-webx-cart::add>');

        $this->assertStringContainsString('<form method="post" action="'.route('webx-cart.items.store').'"', $html);
        $this->assertStringContainsString('name="_token" value="'.csrf_token().'"', $html);
        $this->assertStringContainsString('name="items[0][type]" value="test.product"', $html);
        $this->assertStringContainsString('name="items[0][ref]" value="2"', $html);
        $this->assertStringContainsString('name="items[0][qty]" value="1"', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('class="webx-cart-add buy"', $html);
        $this->assertStringContainsString('data-webx-cart-add="test.product:2"', $html);
        $this->assertStringContainsString('>Buy</button>', $html);
        $this->assertStringNotContainsString('is-in-cart', $html);
    }

    #[Test]
    public function a_record_already_in_the_cart_is_marked_and_the_default_word_is_the_carts(): void
    {
        $result = $this->app->make(CartService::class)->add(null, [['type' => 'test.product', 'ref' => 2]], 'en');
        $this->withCartCookie((string) $result->token);

        $html = Blade::render('<x-webx-cart::add type="test.product" :ref="2" />');

        $this->assertStringContainsString('class="webx-cart-add is-in-cart"', $html);
        $this->assertStringContainsString('>Add to cart</button>', $html);
        $this->assertStringContainsString('class="webx-cart-add"', Blade::render('<x-webx-cart::add type="test.product" :ref="3" />'));
    }
}
