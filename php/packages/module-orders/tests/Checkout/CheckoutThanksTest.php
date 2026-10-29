<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Orders\Checkout\PlacedOrders;
use WebxUi\Orders\Models\Order;

/**
 * «Thank you» (spec §26): for the session that placed the order and nobody else; the signed-in
 * owner goes to the order's page. Without the notifications nothing promises a letter.
 */
final class CheckoutThanksTest extends CheckoutTestCase
{
    #[Test]
    public function thank_you_is_the_order_for_the_session_that_placed_it(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $this->submit($cart)->assertRedirect();
        $order = Order::query()->sole();

        $page = $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('Order '.$order->number.' is placed')
            ->assertSee('Item A')
            ->assertSee('Order page')
            ->assertSee('data-number="'.$order->number.'"', false)
            // No module of notifications — no letter promised.
            ->assertDontSee('We sent a confirmation');
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));

        // Another session: no such page.
        $this->app['session.store']->forget(PlacedOrders::SESSION);
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertNotFound();
        $this->visit($cart, '/checkout/thanks/NOPE')->assertNotFound();
    }

    #[Test]
    public function the_signed_in_owner_goes_to_the_orders_page(): void
    {
        $cart = $this->cart();
        $order = $this->place($cart, ['userId' => 7]);
        $this->app->instance(Visitors::class, new class implements Visitors
        {
            public function current(): Visitor
            {
                return new Visitor(7, 'visitor@example.test', true, 'Visitor', 'B', null);
            }

            public function find(int $id): ?Visitor
            {
                return null;
            }

            public function findByEmail(string $email): ?Visitor
            {
                return null;
            }

            public function signInUrl(?string $back = null): ?string
            {
                return null;
            }
        });

        $redirect = $this->visit($cart, '/checkout/thanks/'.$order->number)->assertRedirect();
        $this->assertStringContainsString('/order/'.$order->number.'?signature=', (string) $redirect->headers->get('Location'));
    }
}
