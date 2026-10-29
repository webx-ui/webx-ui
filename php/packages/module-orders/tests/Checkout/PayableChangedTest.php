<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Tests\Fakes\FakeSources;

/**
 * The amount the buyer saw against the one the order comes to (spec §24.4, decision 26): more —
 * a new confirmation; less — the order with it at once, told on «Thank you»; changed twice in one
 * press — a new confirmation too, there is no third try.
 */
final class PayableChangedTest extends CheckoutTestCase
{
    /** @var list<int> the discount of each count of the chain from now on, in turn; the last stays */
    private array $discounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(AdjustmentSources::class)->register(new FakeSources(function (): array {
            $discount = count($this->discounts) > 1 ? array_shift($this->discounts) : ($this->discounts[0] ?? 0);

            return $discount === 0 ? [] : [new Adjustment('rule#1', 'Promo', -$discount)];
        }));
    }

    #[Test]
    public function more_than_the_buyer_saw_is_a_new_confirmation_with_the_same_key(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $fields = $this->hidden($cart);
        $this->assertSame('4999', $fields['expected_payable']);

        // The price went up while the buyer read the page.
        $this->products->lines[1] = self::product('Item A', 5499);
        $this->travel(5)->seconds();

        $this->visit($cart, '/checkout/place', $fields, json: true)
            ->assertStatus(409)
            ->assertJsonPath('code', 'checkout.payable_changed')
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, '49,99') && str_contains($message, '54,99'))
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'name="expected_payable" value="5499"')
                && str_contains($html, 'name="place_key" value="'.$fields['place_key'].'"'));
        $this->assertSame(0, Order::query()->count());

        // Pressed again with the amount now on the screen — the same key, an order.
        $this->visit($cart, '/checkout/place', [...$fields, 'expected_payable' => '5499'], json: true)->assertOk();

        $order = Order::query()->sole();
        $this->assertSame(5499, $order->payable);
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()->assertDontSee('less');
    }

    #[Test]
    public function less_than_the_buyer_saw_is_the_order_with_the_smaller_amount(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $fields = $this->hidden($cart);

        // A rule came into force while the buyer read the page.
        $this->discounts = [500];
        $this->travel(5)->seconds();

        $placed = $this->visit($cart, '/checkout/place', $fields)->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame(4499, $order->payable);
        $placed->assertRedirect(url('/checkout/thanks/'.$order->number));
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('The order came to 5,00')
            ->assertSee('less.');
        // The difference is the page's, not the order's.
        $this->assertNull(CheckoutDraft::query()->findOrFail($cart->cart->id)->shown_payable);
    }

    #[Test]
    public function changed_twice_in_one_press_is_a_new_confirmation(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $fields = $this->hidden($cart);

        // The count of the page, the first place() and the second one each see another amount:
        // less first, then more again — no third try.
        $this->discounts = [0, 500, 200];
        // The request counts the cart afresh, as a site does; a test's container outlives requests.
        $this->app->make(CheckoutContexts::class)->forget();
        $this->travel(5)->seconds();

        $this->visit($cart, '/checkout/place', $fields, json: true)
            ->assertStatus(409)
            ->assertJsonPath('code', 'checkout.payable_changed')
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, '49,99') && str_contains($message, '47,99'));

        $this->assertSame(0, Order::query()->count());
    }
}
