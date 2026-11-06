<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Models\Order;

/**
 * «Order contents» of the column (spec §23.5): the controls of a line, the cart's own API behind
 * them with JavaScript and without it, the column and the slots counted again from the new lines,
 * and an order placed after the change.
 */
final class CheckoutCartEditTest extends CheckoutTestCase
{
    use UsesFakeSlots;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installSlots();
    }

    /**
     * The cart's API as a page sends it: the method in the form, the answer by what it accepts.
     *
     * @param  array<string, mixed>  $post
     * @return TestResponse<Response>
     */
    private function write(AddResult $cart, CartItem $item, string $method, array $post = [], bool $json = true): TestResponse
    {
        $this->flushHeaders();
        $this->app->make(CheckoutContexts::class)->forget();
        $request = $this->withCookie('webx_cart', (string) $cart->token)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->withHeader('Referer', url('/checkout'));

        if ($json) {
            $request = $request->withHeader('Accept', 'application/json');
        }

        return $request->post('/api/cart/items/'.$item->id, ['_method' => $method, ...$post]);
    }

    private function item(AddResult $cart, int $ref = 1): CartItem
    {
        return CartItem::query()->where('cart_id', $cart->cart->id)->where('ref_id', $ref)->firstOrFail();
    }

    #[Test]
    public function a_stored_line_has_a_stepper_and_a_removal_with_their_labels(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000, maxQty: 3);
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]);
        $item = $this->item($cart);

        $html = (string) $this->visit($cart, '/checkout')->assertOk()->getContent();

        $this->assertStringContainsString('<details class="webx-orders-checkout__lines" open>', $html);
        $this->assertStringContainsString('action="'.url('/api/cart/items/'.$item->id).'" data-max="3"', $html);
        $this->assertStringContainsString('name="qty" value="1" data-control="decrease" aria-label="One less: Item A"', $html);
        $this->assertStringContainsString('name="qty" value="3" data-control="increase" aria-label="One more: Item A"', $html);
        $this->assertStringContainsString('data-control="remove" aria-label="Remove: Item A">Remove</button>', $html);
        $this->assertStringContainsString('name="_method" value="PATCH"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
    }

    #[Test]
    public function the_buttons_stop_at_one_and_at_the_stock(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000, maxQty: 1);
        $cart = $this->cart();

        $html = (string) $this->visit($cart, '/checkout')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~data-control="decrease"[^>]*disabled~', $html);
        $this->assertMatchesRegularExpression('~data-control="increase"[^>]*disabled~', $html);
    }

    #[Test]
    public function a_new_quantity_counts_the_column_and_the_delivery_again(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000);
        $cart = $this->cart();
        $this->shipping->choose('courier', new ShippingLine(ShippingLine::FIXED, 500));

        $this->write($cart, $this->item($cart), 'PATCH', ['qty' => 3])->assertOk()->assertJsonPath('units', 3);

        $html = (string) $this->visit($cart, '/checkout/summary')->assertOk()->getContent();
        $this->assertStringContainsString('name="expected_payable" value="3500"', $html);
        $this->assertStringContainsString('<span class="webx-orders-checkout__qty-value" data-qty>3</span>', $html);
    }

    #[Test]
    public function a_cart_with_nothing_to_ship_any_more_has_no_delivery_step(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000);
        $this->products->lines[2] = self::product('Item B', 2000, shippable: false);
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 2]]);

        $this->assertStringContainsString('data-step="delivery"', (string) $this->visit($cart, '/checkout')->getContent());

        $this->write($cart, $this->item($cart), 'DELETE')->assertOk();

        $html = (string) $this->visit($cart, '/checkout')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-step="delivery"', $html);
        $this->assertStringContainsString('data-step="payment"', $html);
        $this->assertStringNotContainsString('Item A', $html);
    }

    #[Test]
    public function the_stock_refuses_what_it_does_not_have_and_the_cart_stays(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000, maxQty: 2);
        $cart = $this->cart();

        $this->write($cart, $this->item($cart), 'PATCH', ['qty' => 3])->assertStatus(422)->assertJsonValidationErrors('qty');

        $this->assertSame(1, $this->item($cart)->qty);
    }

    #[Test]
    public function without_javascript_a_press_comes_back_to_the_checkout(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000, maxQty: 2);
        $cart = $this->cart();

        $this->write($cart, $this->item($cart), 'PATCH', ['qty' => 2], json: false)->assertRedirect(url('/checkout'));
        $this->assertSame(2, $this->item($cart)->qty);

        // A refusal comes back with its words above the lines.
        $this->write($cart, $this->item($cart), 'PATCH', ['qty' => 3], json: false)->assertRedirect(url('/checkout'));
        $this->assertStringContainsString('webx-orders-checkout__line-error', (string) $this->visit($cart, '/checkout')->getContent());
    }

    #[Test]
    public function the_last_line_removed_leaves_the_page_of_an_empty_cart_and_the_draft(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000);
        $cart = $this->cart();
        $this->fill($cart);

        $this->write($cart, $this->item($cart), 'DELETE', json: false)->assertRedirect(url('/checkout'));

        $html = (string) $this->visit($cart, '/checkout')->assertOk()->getContent();
        $this->assertStringContainsString('webx-orders-checkout is-empty', $html);
        $this->assertDatabaseHas('order_checkout_drafts', ['cart_id' => $cart->cart->id]);
    }

    #[Test]
    public function the_order_placed_after_the_change_has_the_new_lines(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000);
        $this->products->lines[2] = self::product('Item B', 2000);
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 2]]);
        $this->shipping->choose('courier', new ShippingLine(ShippingLine::FIXED, 500));
        $this->payment->choose('card', online: false);
        $this->fill($cart);
        // The page was open before the change: the key of a repeat stays good, the amount is the new one.
        $this->visit($cart, '/checkout')->assertOk();

        $this->write($cart, $this->item($cart), 'PATCH', ['qty' => 4])->assertOk();
        $this->write($cart, $this->item($cart, 2), 'DELETE')->assertOk();

        $this->submit($cart)->assertRedirect();

        $order = Order::query()->with('items')->sole();
        $this->assertSame([['Item A', 4]], $order->items->map(static fn ($item): array => [$item->name['en'] ?? null, $item->qty])->all());
        $this->assertSame(4500, $order->payable);
    }
}
