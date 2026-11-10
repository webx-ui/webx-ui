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
 * «Order contents» of the column (spec §23.5): a list without controls, «Edit» and «Open the cart»
 * to the dialog of the cart's widget the page prints, the cart's own API behind that dialog, the
 * column and the slots counted again from the new lines, and an order placed after the change.
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
    public function the_contents_list_the_lines_and_edit_opens_the_dialog_of_the_cart(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000, maxQty: 3);
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]);

        $html = (string) $this->visit($cart, '/checkout')->assertOk()->getContent();

        $this->assertStringContainsString('<details class="webx-orders-checkout__lines" open>', $html);
        $this->assertStringContainsString('Item A <span class="webx-orders-checkout__line-qty">× 2</span>', $html);
        $this->assertStringContainsString('<a class="webx-orders-checkout__edit" href="#webx-cart" data-webx-cart-edit aria-haspopup="dialog">Edit</a>', $html);
        // No control of the cart in the column: the dialog changes the lines.
        $this->assertStringNotContainsString('data-control=', $html);
        $this->assertStringNotContainsString('/api/cart/items/', $html);
        // The minimal layout has no cart: the page prints the widget's dialog without its trigger.
        $this->assertMatchesRegularExpression('~<dialog[^>]*id="webx-cart"~', $html);
        $this->assertStringContainsString('data-webx-cart-panel="webx-cart"', $html);
        $this->assertStringNotContainsString('webx-cart__trigger', $html);
        // Without JavaScript the link shows the dialog through :target, with the cart's own note.
        $this->assertStringContainsString('Turn on JavaScript to see your cart', $html);
    }

    #[Test]
    public function a_line_that_cannot_be_bought_is_marked_and_the_warning_opens_the_cart(): void
    {
        $this->products->lines[1] = self::product('Item A', 1000);
        $this->products->lines[2] = self::product('Item B', 2000);
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 2]]);
        // Out of sale after it was put in the cart.
        $this->products->lines[2] = self::product('Item B', 2000, available: false);

        $html = (string) $this->visit($cart, '/checkout')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<li class="webx-orders-checkout__line is-unavailable" data-line="\d+">.*?Item B.*?<span class="webx-orders-checkout__gone">Cannot be bought now</span>~s', $html);
        $this->assertStringContainsString('<a href="#webx-cart" data-webx-cart-edit aria-haspopup="dialog">Open the cart</a>', $html);
        $this->assertStringNotContainsString('data-webx-dialog="webx-cart"', $html);
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
        $this->assertStringContainsString('<span class="webx-orders-checkout__line-qty">× 3</span>', $html);
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
