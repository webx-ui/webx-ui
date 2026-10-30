<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Models\Order;

/**
 * The order of the steps (spec §20.2, §34): without slots — contacts and comment; with delivery
 * and payment — by their positions, payment after delivery and seeing what was chosen there;
 * without a line that goes by mail — no delivery at all.
 */
final class StepOrderTest extends CheckoutTestCase
{
    use UsesFakeSlots;

    #[Test]
    public function without_slots_the_steps_are_the_contacts_and_the_comment(): void
    {
        $cart = $this->cart();

        $this->assertSame(['contacts' => 100, 'comment' => 900], $this->app->make(CheckoutSteps::class)->positions());
        $this->visit($cart, '/checkout')->assertOk()
            ->assertSeeInOrder(['id="checkout-step-contacts"', 'id="checkout-step-comment"', 'id="checkout-summary"'], false)
            ->assertDontSee('checkout-step-delivery', false)
            ->assertDontSee('checkout-step-payment', false)
            ->assertDontSee('Delivery cost');
    }

    #[Test]
    public function the_slots_stand_by_their_positions(): void
    {
        $this->installSlots();
        $cart = $this->cart();

        $this->assertSame(
            ['contacts' => 100, 'comment' => 900, 'delivery' => 200, 'payment' => 300],
            $this->app->make(CheckoutSteps::class)->positions(),
        );
        $this->visit($cart, '/checkout')->assertOk()
            ->assertSeeInOrder([
                'id="checkout-step-contacts"',
                'id="checkout-step-delivery"', '2. Delivery', 'class="fake-delivery"',
                'id="checkout-step-payment"', '3. Payment', 'class="fake-payment"',
                'id="checkout-step-comment"', '4. Comment',
                'id="checkout-summary"',
            ], false)
            ->assertSee('data-step="delivery"  data-slot', false)
            ->assertSee('data-slot-event="webx:checkout:slot-changed"', false)
            ->assertSee('data-summary="'.url('/checkout/summary').'"', false);
    }

    #[Test]
    public function payment_knows_the_chosen_delivery_and_nothing_else_of_it(): void
    {
        $this->installSlots();
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()->assertDontSee('Cash on delivery');
        $this->assertNull($this->payment->seen[0]);

        $this->shipping->choose('carrier-a-branch', new ShippingLine(ShippingLine::FIXED, 500), carrier: 'carrier-a', cod: true);
        $this->visit($cart, '/checkout')->assertOk()->assertSee('Cash on delivery');

        $choice = $this->payment->seen[1];
        $this->assertNotNull($choice);
        $this->assertSame(['carrier-a-branch', 'carrier-a', true, false], [$choice->method, $choice->carrier, $choice->cashOnDelivery, $choice->storePickup]);
    }

    #[Test]
    public function without_a_line_that_goes_by_mail_there_is_no_delivery(): void
    {
        $this->installSlots();
        $this->products->lines[5] = self::product('Service E', 3000, shippable: false);
        $cart = $this->cart([['type' => 'test.product', 'ref' => 5]]);

        $this->visit($cart, '/checkout')->assertOk()
            ->assertSeeInOrder(['id="checkout-step-contacts"', 'id="checkout-step-payment"', '2. Payment', 'id="checkout-step-comment"'], false)
            ->assertDontSee('checkout-step-delivery', false)
            ->assertDontSee('Delivery cost');

        // The delivery is not asked even when it would refuse: it is not a step of this order.
        $this->shipping->pointGone = true;
        $this->fill($cart);
        $this->payment->choose('cash', online: false, title: 'Cash');
        $this->submit($cart, json: true)->assertOk();

        $order = Order::query()->sole();
        $this->assertNull($order->delivery);
        $this->assertSame('cash', $order->payment['method'] ?? null);
        $this->assertSame([], $this->shipping->checked);
    }
}
