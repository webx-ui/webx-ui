<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Orders\Checkout\CheckoutAntispam;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Panel\OrderList;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * «Buy in one click» (spec §29, decision 62): an order of one line from the product's page, the
 * buyer's cart and draft untouched, nothing left behind by a refusal at any step, and the
 * stricter antispam of §28 — the mark, the hidden field, the origin, a few orders an hour.
 */
final class QuickOrderTest extends CheckoutTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SettingsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Settings::class)->save(['orders.checkout.quick.enabled' => 'on']);
    }

    #[Test]
    public function the_button_stands_only_while_the_setting_is_on(): void
    {
        $html = Blade::render('<x-webx-orders::checkout.quick-button type="test.product" :ref="1" />');

        $this->assertStringContainsString('data-webx-dialog="webx-quick-test-product-1"', $html);
        $this->assertStringContainsString('action="http://localhost/checkout/quick"', $html);
        $this->assertStringContainsString('name="phone"', $html);
        $this->assertStringContainsString('name="'.CheckoutAntispam::TIMESTAMP.'"', $html);
        // The page may be cached for everybody: no token minted for one visitor.
        $this->assertStringNotContainsString('name="_token"', $html);

        $this->app->make(Settings::class)->save(['orders.checkout.quick.enabled' => 'off']);

        $this->assertSame('', trim(Blade::render('<x-webx-orders::checkout.quick-button type="test.product" :ref="1" />')));
        $this->quick(['phone' => '+800 1234 5678'])->assertNotFound();
    }

    #[Test]
    public function an_order_comes_from_the_product_and_the_buyers_cart_stays_as_it_was(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 2]]);
        $this->fill($cart);
        $draft = CheckoutDraft::query()->findOrFail($cart->cart->id)->toArray();
        $fields = [...$this->dialog(), 'qty' => 2, 'phone' => '+800 1234 0001'];
        $this->travel(5)->seconds();

        $response = $this->visit($cart, '/checkout/quick', $fields, json: true)
            ->assertOk();

        $order = Order::query()->sole();
        $this->assertSame('Buyer', $order->customer_first_name);
        $this->assertNull($order->customer_last_name);
        $this->assertNull($order->customer_email);
        $this->assertSame('+800 1234 0001', $order->customer_phone);
        $this->assertSame(9998, $order->payable);
        $this->assertSame([[1, 2]], $order->items->map(static fn ($item): array => [(int) $item->ref_id, $item->qty])->all());
        $this->assertSame('quick', $order->milestones()->where('milestone', Milestone::Placed->value)->value('source'));
        $this->assertStringContainsString('We will call you back within 15 minutes', (string) $response->json('heading'));
        $this->assertStringContainsString("Order {$order->number} for", (string) $response->json('message'));

        // The service cart went; the buyer's is the only one, its line and its draft as they were.
        $this->assertSame([$cart->cart->id], Cart::query()->pluck('id')->all());
        $this->assertSame([2], CartItem::query()->pluck('ref_id')->map(intval(...))->all());
        $this->assertEquals($draft, CheckoutDraft::query()->findOrFail($cart->cart->id)->toArray());
    }

    #[Test]
    public function a_name_given_is_the_order_s_and_none_is_buyer_in_the_order_s_language(): void
    {
        $this->quick(['phone' => '+800 1234 0002', 'name' => 'Anna'])->assertOk();
        $this->quick(['phone' => '+800 1234 0003'], '/ru')->assertOk();

        $this->assertSame(['Anna', 'Покупатель'], Order::query()->orderBy('id')->pluck('customer_first_name')->all());
        $this->assertSame('ru', Order::query()->latest('id')->value('locale'));
    }

    #[Test]
    public function a_refusal_at_any_step_leaves_neither_a_cart_nor_an_order(): void
    {
        // The line: gone, then not for sale.
        $this->quick(['ref' => 99, 'phone' => '+800 1234 0004'])->assertUnprocessable()->assertJsonValidationErrors('quick');
        $this->products->lines[1] = self::product('Item A', 4999, available: false);
        $this->quick(['phone' => '+800 1234 0004'])->assertUnprocessable()->assertJsonValidationErrors('quick');
        $this->products->lines[1] = self::product('Item A', 4999);

        // The phone.
        $this->quick(['phone' => 'call me'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->quick([])->assertUnprocessable()->assertJsonValidationErrors('phone');

        // The order, refused inside its transaction after the cart was written.
        Event::listen(OrderPlacing::class, static fn () => throw new OrderRefused('test.refused', 'Not today.'));
        $this->quick(['phone' => '+800 1234 0004'])->assertUnprocessable()->assertJsonValidationErrors(['quick' => 'Not today.']);

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function the_same_dialog_sent_twice_is_one_order(): void
    {
        $first = $this->quick(['phone' => '+800 1234 0005'])->assertOk()->json('message');
        $second = $this->quick(['phone' => '+800 1234 0005'])->assertOk()->json('message');

        $this->assertSame($first, $second);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, Cart::query()->count());
    }

    #[Test]
    public function a_phone_and_an_address_make_so_many_orders_an_hour(): void
    {
        foreach ([1, 2, 3] as $qty) {
            $this->quick(['qty' => $qty, 'phone' => '+800 1234 0006'])->assertOk();
        }

        // The same phone from another address, and another phone from the same address.
        $this->quick(['qty' => 4, 'phone' => '+800 1234 0006'], ip: '203.0.113.9')->assertStatus(429)->assertJsonValidationErrors('quick');
        $this->quick(['qty' => 4, 'phone' => '+800 1234 0007'])->assertStatus(429);
        $this->quick(['qty' => 4, 'phone' => '+800 1234 0007'], ip: '203.0.113.9')->assertOk();

        $this->assertSame(4, Order::query()->count());
        $this->travel(61)->minutes();
        $this->quick(['qty' => 5, 'phone' => '+800 1234 0006'])->assertOk();
    }

    #[Test]
    public function the_antispam_turns_a_robot_away_and_writes_nothing(): void
    {
        // The hidden field filled: answered as if it worked.
        $this->quick(['phone' => '+800 1234 0008', CheckoutAntispam::HONEYPOT => 'bot'])->assertOk()->assertJsonPath('heading', fn (?string $heading): bool => str_contains((string) $heading, 'call you back'));
        // No mark, a mark too fresh, somebody else's page.
        $this->quick(['phone' => '+800 1234 0008', CheckoutAntispam::TIMESTAMP => ''])->assertUnprocessable()->assertJsonValidationErrors('quick');
        $this->quick(['phone' => '+800 1234 0008'], wait: 0)->assertUnprocessable();
        $this->quick(['phone' => '+800 1234 0008'], headers: ['Origin' => 'https://elsewhere.example.test'])->assertUnprocessable();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Cart::query()->count());
    }

    #[Test]
    public function a_page_cached_with_a_stale_token_still_orders_and_without_javascript_comes_back_answered(): void
    {
        // No CSRF middleware switched off here: the route itself goes without it.
        $fields = [...$this->dialog(), 'phone' => '+800 1234 0009', FormResponse::FORM => 'webx-quick-test-product-1-form'];
        $this->travel(5)->seconds();

        $this->from('http://localhost/products/item-a')->post('/checkout/quick', $fields)
            ->assertSessionHasNoErrors()
            ->assertRedirect('http://localhost/products/item-a')
            ->assertSessionHas(FormResponse::FLASH, fn (array $answer): bool => $answer['form'] === 'webx-quick-test-product-1-form'
                && str_contains((string) $answer['message'], 'Order '));

        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function the_terms_asked_with_a_checkbox_must_be_agreed_with(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.terms' => 'checkbox']);

        $this->quick(['phone' => '+800 1234 0010'])->assertUnprocessable()->assertJsonValidationErrors('terms');
        $this->quick(['phone' => '+800 1234 0010', 'terms' => '1'])->assertOk();
    }

    #[Test]
    public function the_section_marks_and_filters_the_one_click_orders(): void
    {
        $this->place($this->cart());
        $this->quick(['phone' => '+800 1234 0011'])->assertOk();
        $list = $this->app->make(OrderList::class);

        $quick = $list->page(['source' => 'quick'], 25);
        $this->assertSame(1, $quick->total());
        $this->assertSame('quick', $quick->items()[0]->getAttribute('placed_source'));
        $this->assertSame(1, $list->page(['source' => 'checkout'], 25)->total());
        $this->assertSame(['all' => 1, 'new' => 1, 'awaiting' => 0, 'failed' => 0], $list->counts(['source' => 'quick']));
    }

    /**
     * The dialog's hidden fields as the button prints them, the mark drawn a few seconds ago.
     *
     * @return array<string, mixed>
     */
    private function dialog(): array
    {
        return [
            'type' => 'test.product',
            'ref' => '1',
            'qty' => '1',
            CheckoutAntispam::HONEYPOT => '',
            CheckoutAntispam::TIMESTAMP => $this->app->make(CheckoutAntispam::class)->timestamp(),
        ];
    }

    /**
     * @param  array<string, mixed>  $post  over the dialog's fields
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    private function quick(array $post, string $prefix = '', int $wait = 5, string $ip = '127.0.0.1', array $headers = []): TestResponse
    {
        $fields = [...$this->dialog(), ...$post];
        $this->travel($wait)->seconds();
        $this->flushHeaders();

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['Accept' => 'application/json', ...$headers])
            ->post($prefix.'/checkout/quick', $fields);
    }
}
