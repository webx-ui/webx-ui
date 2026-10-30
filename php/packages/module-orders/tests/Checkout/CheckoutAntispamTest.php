<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartService;
use WebxUi\Orders\Checkout\CheckoutAntispam;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Models\Order;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * What guards the checkout for nothing (spec §28): the field nobody sees, the mark of time, the
 * origin, the limits.
 */
final class CheckoutAntispamTest extends CheckoutTestCase
{
    #[Test]
    public function the_honeypot_filled_is_neither_an_order_nor_an_error(): void
    {
        $cart = $this->cart();
        $this->fill($cart);

        $this->visit($cart, '/checkout')->assertSee('name="'.CheckoutAntispam::HONEYPOT.'"', false);

        $this->submit($cart, [CheckoutAntispam::HONEYPOT => 'http://spam.example.test'])
            ->assertRedirect(url('/checkout'))
            ->assertSessionMissing(FormResponse::FLASH)
            ->assertSessionHasNoErrors();
        $this->submit($cart, [CheckoutAntispam::HONEYPOT => 'x'], json: true)
            ->assertOk()
            ->assertJsonPath('redirect', url('/checkout'));

        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function the_honeypot_of_the_contacts_keeps_nothing(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => 'Bot', 'phone' => '+800 1234 5678'],
            CheckoutAntispam::HONEYPOT => 'x',
        ])->assertRedirect(url('/checkout').'#checkout-step-contacts');

        $this->assertSame(0, CheckoutDraft::query()->count());
    }

    #[Test]
    public function a_confirmation_too_soon_or_without_its_mark_is_turned_away(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $fields = $this->hidden($cart);

        // At once after the page was drawn: no person is that fast.
        $this->visit($cart, '/checkout/place', $fields, json: true)
            ->assertStatus(422)
            ->assertJsonPath('code', 'checkout.retry')
            ->assertJsonPath('message', 'Something went wrong. Try again.');
        $this->travel(5)->seconds();
        $this->visit($cart, '/checkout/place', [...$fields, CheckoutAntispam::TIMESTAMP => ''], json: true)->assertStatus(422);
        $this->visit($cart, '/checkout/place', [...$fields, CheckoutAntispam::TIMESTAMP => 'forged'], json: true)->assertStatus(422);

        $this->assertSame(0, Order::query()->count());
        $this->visit($cart, '/checkout/place', $fields, json: true)->assertOk();
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function another_sites_page_is_turned_away(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $fields = $this->hidden($cart);
        $this->travel(5)->seconds();

        $this->visit($cart, '/checkout/place', $fields, json: true, headers: ['Origin' => 'https://elsewhere.example.test'])->assertForbidden()->assertJsonPath('code', 'checkout.origin');
        $this->assertSame(0, Order::query()->count());

        $this->visit($cart, '/checkout/place', $fields, json: true, headers: ['Origin' => url('/')])->assertOk();
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function a_cart_makes_so_many_orders_an_hour(): void
    {
        config(['webx-orders.checkout.throttle.orders_per_hour' => 1]);
        $cart = $this->cart();
        $this->fill($cart);
        $this->submit($cart)->assertRedirect();

        $this->app->make(CartService::class)->add($cart->cart, [['type' => 'test.product', 'ref' => 3]], 'en');
        $this->app->make(CheckoutContexts::class)->forget();

        $this->submit($cart, json: true)->assertStatus(429)->assertJsonPath('code', 'checkout.too_many');
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function an_address_presses_the_button_so_many_times_a_minute(): void
    {
        config(['webx-orders.checkout.throttle.place_per_minute' => 2]);
        $cart = $this->cart();

        $this->visit($cart, '/checkout/place', [], json: true)->assertStatus(422);
        $this->visit($cart, '/checkout/place', [], json: true)->assertStatus(422);
        $this->visit($cart, '/checkout/place', [], json: true)->assertStatus(429);
    }
}
