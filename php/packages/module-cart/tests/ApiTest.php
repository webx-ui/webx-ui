<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Cart\Http\CartController;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Tests\Fakes\FakeSources;
use WebxUi\Cart\Totals\GeneratedLine;
use WebxUi\Cart\Totals\LineSources;
use WebxUi\Routing\Reserved;

/**
 * The JSON API (§6): every answer the whole state with its `*_formatted` strings, a batch all or
 * nothing, a line only of the visitor's own cart, a form without JavaScript, and the guards.
 */
final class ApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A JSON request of a test carries cookies only when told to, as a browser's fetch does.
        $this->withCredentials();
    }

    #[Test]
    public function reading_without_a_cookie_answers_an_empty_cart_and_writes_nothing(): void
    {
        $response = $this->getJson('/api/cart')->assertOk();

        $response->assertExactJson([
            'currency' => 'EUR',
            'exponent' => 2,
            'locale' => 'en',
            'lines' => [],
            'units' => 0,
            'subtotal' => 0,
            'subtotal_formatted' => "0,00\u{00A0}€",
            'adjustments' => [],
            'total' => 0,
            'total_formatted' => "0,00\u{00A0}€",
            'tenders' => [],
            'payable' => 0,
            'payable_formatted' => "0,00\u{00A0}€",
            'benefits' => [],
            'hints' => [],
            'warnings' => [],
        ]);
        $response->assertCookieMissing('webx_cart');
        $this->assertSame(0, Cart::query()->count());
        $this->assertPrivate($response);
    }

    #[Test]
    public function a_batch_goes_in_and_every_answer_is_the_whole_state(): void
    {
        $response = $this->postJson('/api/cart/items', ['items' => [
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2],
            ['type' => 'test.product', 'ref' => 2],
        ]])->assertOk();

        $ids = CartItem::query()->orderBy('id')->pluck('id')->all();
        $response->assertJsonPath('added', $ids)
            ->assertJsonPath('units', 3)
            ->assertJsonPath('lines.0.name', 'Item A')
            ->assertJsonPath('lines.0.price', 4999)
            ->assertJsonPath('lines.0.price_formatted', "49,99\u{00A0}€")
            ->assertJsonPath('lines.0.line_total', 9998)
            ->assertJsonPath('lines.0.line_total_formatted', "99,98\u{00A0}€")
            ->assertJsonPath('subtotal', 24998)
            ->assertJsonPath('subtotal_formatted', "249,98\u{00A0}€")
            ->assertJsonPath('payable_formatted', "249,98\u{00A0}€");
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertPrivate($response);

        $token = $this->tokenOf($response);
        $this->assertNotNull($token);

        // The price is the provider's now, not the one the line went in at.
        $this->products->lines[1] = self::product('Item A', 3999);

        $this->withCookie('webx_cart', $token)->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('lines.0.price', 3999)
            ->assertJsonPath('lines.0.price_at_add', 4999)
            ->assertJsonPath('lines.0.price_change', 'down')
            ->assertJsonPath('warnings.0.code', 'price.down');
    }

    #[Test]
    public function one_bad_line_and_nothing_of_the_batch_is_written(): void
    {
        $this->postJson('/api/cart/items', ['items' => [
            ['type' => 'test.product', 'ref' => 1],
            ['type' => 'test.product', 'ref' => 404],
        ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.ref'])
            ->assertCookieMissing('webx_cart');
        $this->postJson('/api/cart/items', ['items' => [
            ['type' => 'test.product', 'ref' => 1],
            ['type' => 'nobody.sells', 'ref' => 1],
        ]])->assertStatus(422)->assertJsonValidationErrors(['items.1.type']);

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    #[Test]
    public function a_bundle_goes_in_as_a_tree_and_a_grandchild_is_refused(): void
    {
        $this->postJson('/api/cart/items', ['items' => [
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'parent' => 'set', 'qty' => 2],
        ]])
            ->assertOk()
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.children.0.name', 'Item B')
            ->assertJsonPath('lines.0.children.0.effective_qty', 2)
            ->assertJsonPath('units', 3);

        $this->postJson('/api/cart/items', ['items' => [
            ['type' => 'test.product', 'ref' => 1, 'key' => 'a'],
            ['type' => 'test.product', 'ref' => 2, 'key' => 'b', 'parent' => 'a'],
            ['type' => 'test.product', 'ref' => 3, 'parent' => 'b'],
        ]])->assertStatus(422)->assertJsonValidationErrors(['items.2.parent']);
    }

    #[Test]
    public function a_line_changes_and_goes_and_another_visitors_line_is_not_found(): void
    {
        $mine = $this->tokenOf($this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1]]]));
        $line = (int) CartItem::query()->value('id');

        $this->defaultCookies = [];
        $theirs = $this->tokenOf($this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 2]]]));
        $their = (int) CartItem::query()->where('id', '!=', $line)->value('id');

        $this->defaultCookies = [];
        $this->withCookie('webx_cart', (string) $mine)->patchJson("/api/cart/items/{$line}", ['qty' => 3])
            ->assertOk()
            ->assertJsonPath('lines.0.qty', 3)
            ->assertJsonPath('units', 3);
        $this->withCookie('webx_cart', (string) $mine)->patchJson("/api/cart/items/{$line}", ['qty' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qty']);

        // Somebody else's line is as missing as one that never was.
        $this->withCookie('webx_cart', (string) $mine)->patchJson("/api/cart/items/{$their}", ['qty' => 2])->assertNotFound();
        $this->withCookie('webx_cart', (string) $mine)->deleteJson("/api/cart/items/{$their}")->assertNotFound();
        $this->defaultCookies = [];
        $this->patchJson("/api/cart/items/{$line}", ['qty' => 2])->assertNotFound();
        $this->assertSame(1, CartItem::query()->whereKey($their)->value('qty'));

        $this->withCookie('webx_cart', (string) $mine)->deleteJson("/api/cart/items/{$line}")
            ->assertOk()
            ->assertJsonPath('lines', [])
            ->assertJsonPath('units', 0);
        $this->assertNotNull($theirs);
    }

    #[Test]
    public function a_generated_line_is_declined_by_its_key_unless_it_may_not_be(): void
    {
        $this->app->make(LineSources::class)->register(new FakeSources(static fn (): array => [
            new GeneratedLine('rule#12', 'test.product', 3, price: 1, label: 'Gift'),
            new GeneratedLine('rule#13', 'test.product', 3, price: 1, declinable: false),
        ]));
        $token = (string) $this->tokenOf($this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1]]]));

        $this->withCookie('webx_cart', $token)->getJson('/api/cart')
            ->assertJsonPath('lines.1.id', 'rule#12:0')
            ->assertJsonPath('lines.1.declinable', true)
            ->assertJsonPath('lines.2.id', 'rule#13:0');

        $this->withCookie('webx_cart', $token)->deleteJson('/api/cart/items/'.rawurlencode('rule#12:0'))
            ->assertOk()
            ->assertJsonCount(2, 'lines')
            ->assertJsonPath('lines.1.id', 'rule#13:0');
        $this->assertSame(['rule#12'], Cart::query()->firstOrFail()->declined_rules);

        $this->withCookie('webx_cart', $token)->deleteJson('/api/cart/items/'.rawurlencode('rule#13:0'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
        $this->withCookie('webx_cart', $token)->deleteJson('/api/cart/items/'.rawurlencode('rule#99:0'))->assertNotFound();
    }

    #[Test]
    public function clearing_takes_the_lines_the_declined_rules_and_the_cookie(): void
    {
        $token = (string) $this->tokenOf($this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1]]]));
        Cart::query()->update(['declined_rules' => json_encode(['rule#1'])]);

        $response = $this->withCookie('webx_cart', $token)->deleteJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('lines', [])
            ->assertJsonPath('total_formatted', "0,00\u{00A0}€");

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
        $this->assertTrue($response->getCookie('webx_cart', false)?->isCleared());
    }

    #[Test]
    public function a_form_without_javascript_comes_back_to_its_page(): void
    {
        $response = $this->from('/item-a')->post('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => '1', 'qty' => '1']]]);

        $response->assertRedirect('/item-a')->assertSessionHas(CartController::FLASH, ['added' => [(int) CartItem::query()->value('id')]]);
        $this->assertNotNull($this->tokenOf($response));

        $this->from('/item-c')->post('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => '404']]])
            ->assertRedirect('/item-c')
            ->assertSessionHasErrors(['items.0.ref']);
    }

    #[Test]
    public function a_write_without_the_csrf_token_is_refused(): void
    {
        // The real check: the framework waves every request through while unit tests run.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1]]])->assertStatus(419);
        $this->assertSame(0, Cart::query()->count());

        // Reading needs no token.
        $this->getJson('/api/cart')->assertOk();
    }

    #[Test]
    public function writes_are_throttled_per_address(): void
    {
        $this->app['config']->set('webx-cart.throttle', 2);

        $this->deleteJson('/api/cart')->assertOk();
        $this->deleteJson('/api/cart')->assertOk();
        $this->deleteJson('/api/cart')->assertStatus(429);
        $this->getJson('/api/cart')->assertOk();
    }

    #[Test]
    public function the_address_is_a_route_so_routing_never_gives_it_to_a_page(): void
    {
        $reserved = new Reserved($this->app->make('router'), $this->app->make('config'));

        $this->assertTrue($reserved->taken('api/cart'));
    }

    /** @param  TestResponse<Response>  $response */
    private function tokenOf(TestResponse $response): ?string
    {
        $cookie = $response->getCookie('webx_cart');

        return $cookie === null || $cookie->isCleared() ? null : $cookie->getValue();
    }

    /** @param  TestResponse<Response>  $response */
    private function assertPrivate(TestResponse $response): void
    {
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertContains('Cookie', $response->baseResponse->getVary());
    }
}
