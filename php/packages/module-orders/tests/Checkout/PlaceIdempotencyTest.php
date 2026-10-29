<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceKeys;
use WebxUi\Orders\Models\Order;

/**
 * A confirmation sent twice is one order (spec §24.3): the key of a repeat, the cart's lock, and
 * «Thank you» of the order already made for whatever comes again.
 */
final class PlaceIdempotencyTest extends CheckoutTestCase
{
    #[Test]
    public function the_same_confirmation_twice_is_one_order(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $fields = $this->hidden($cart);
        $this->travel(5)->seconds();

        $first = $this->visit($cart, '/checkout/place', $fields, json: true)->assertOk();
        $second = $this->visit($cart, '/checkout/place', $fields, json: true)->assertOk();

        $order = Order::query()->sole();
        $thanks = url('/checkout/thanks/'.$order->number);
        $first->assertJsonPath('redirect', $thanks);
        $second->assertJsonPath('redirect', $thanks);
        $this->assertSame(PlaceKeys::hash($fields['place_key']), $order->idempotency_key);
    }

    #[Test]
    public function the_column_keeps_its_key_until_an_order_is_made(): void
    {
        $cart = $this->cart();
        $this->fill($cart);

        // Another tab, the column printed again: the page already open is not made stale.
        $one = $this->hidden($cart)['place_key'];
        $this->assertSame($one, $this->hidden($cart)['place_key']);
        $this->assertSame(PlaceKeys::hash($one), CheckoutDraft::query()->findOrFail($cart->cart->id)->place_key);

        $this->submit($cart)->assertRedirect();

        $this->assertNull(CheckoutDraft::query()->findOrFail($cart->cart->id)->place_key);
        $this->assertNotSame($one, $this->hidden($cart)['place_key']);
    }

    #[Test]
    public function two_tabs_with_other_keys_do_not_order_one_cart_twice(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $first = $this->hidden($cart);

        // The second tab of another session: its own key.
        $this->app['session.store']->forget(PlaceKeys::SESSION);
        CheckoutDraft::query()->whereKey($cart->cart->id)->update(['place_key' => null]);
        $second = $this->hidden($cart);
        $this->assertNotSame($first['place_key'], $second['place_key']);
        $this->travel(5)->seconds();

        $this->visit($cart, '/checkout/place', $second)->assertRedirect();
        // The other one finds the cart empty: no second order, the page again.
        $this->visit($cart, '/checkout/place', $first)->assertRedirect(url('/checkout'));

        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function a_repeat_after_the_key_changed_is_thank_you_of_the_order_made(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $fields = $this->hidden($cart);
        $this->travel(5)->seconds();
        $this->visit($cart, '/checkout/place', $fields)->assertRedirect();
        $order = Order::query()->sole();

        // Back in the browser, the cart filled again, a new key issued — and the old page sent.
        $this->app->make(CartService::class)->add($cart->cart, [['type' => 'test.product', 'ref' => 3]], 'en');
        $this->hidden($cart);

        $this->visit($cart, '/checkout/place', $fields)->assertRedirect(url('/checkout/thanks/'.$order->number));
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function a_key_the_draft_does_not_know_is_a_stale_page(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $fields = $this->hidden($cart);
        $this->travel(5)->seconds();

        $this->visit($cart, '/checkout/place', [...$fields, 'place_key' => str_repeat('a', 64)], json: true)
            ->assertStatus(409)
            ->assertJsonPath('code', 'checkout.stale')
            ->assertJsonPath('message', 'The page is out of date. Check the order and confirm it again.')
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'value="'.$fields['place_key'].'"'));
        $this->visit($cart, '/checkout/place', [...$fields, 'place_key' => ''], json: true)->assertStatus(409);

        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function after_the_order_the_draft_keeps_the_contacts_and_the_cart_lives(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $this->visit($cart, '/checkout/steps/comment', ['comment' => 'Ring twice'])->assertRedirect();

        $this->submit($cart)->assertRedirect();

        $draft = CheckoutDraft::query()->findOrFail($cart->cart->id);
        $this->assertSame('Buyer', $draft->customer['first_name'] ?? null);
        $this->assertSame('buyer@example.test', $draft->customer['email'] ?? null);
        $this->assertNull($draft->comment);
        $this->assertNull($draft->terms_accepted_at);
        $this->assertNull($draft->place_key);
        $this->assertNull($draft->shown_payable);

        // The cart's record and its cookie stay; its lines went into the order.
        $kept = Cart::query()->findOrFail($cart->cart->id);
        $this->assertSame(0, $kept->items()->count());
        $this->visit($cart, '/checkout')->assertOk()->assertSee('Your cart is empty.');
        $this->assertSame('Ring twice', Order::query()->sole()->comment);

        // The same guest's next order: the contacts are there already.
        $this->app->make(CartService::class)->add($kept, [['type' => 'test.product', 'ref' => 3]], 'en');
        // The count of the request before is not this one's: a test's container outlives requests.
        $this->app->make(CheckoutContexts::class)->forget();
        $this->visit($cart, '/checkout')->assertSee('Buyer, +800 1234 5678');
    }
}
