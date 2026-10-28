<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Cart\CartService;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Cart\Http\CartController;
use WebxUi\Cart\Rendering\Assets;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The widget (§9): `<x-webx-cart>` — the trigger with the server's counter, the main view (a drawer
 * or a dialog) and the mini cart under the icon —, the panels' HTML in the API's answer when the
 * widget asks for them, the main view printed open after a buy button without JavaScript, a buy
 * at the maximum answered with the cart and a notice, the checkout button, the script once a page.
 */
final class WidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withCredentials();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
            CartServiceProvider::class,
        ];
    }

    #[Test]
    public function the_drawer_is_a_trigger_and_an_empty_drawer_of_the_widgets(): void
    {
        $html = Blade::render('<x-webx-cart preview="none" />');

        $this->assertStringContainsString('class="webx-cart webx-cart--drawer"', $html);
        $this->assertStringContainsString('data-webx-cart="drawer"', $html);
        $this->assertStringContainsString('data-webx-cart-panel="webx-cart"', $html);
        $this->assertStringContainsString('data-webx-cart-api="/api/cart"', $html);
        $this->assertMatchesRegularExpression('~<a class="webx-cart__trigger" href="#webx-cart" data-webx-dialog="webx-cart" aria-haspopup="dialog">~', $html);
        $this->assertStringContainsString('class="webx-dialog webx-dialog--sheet webx-dialog--drawer webx-cart__dialog"', $html);
        $this->assertMatchesRegularExpression('~<span class="webx-cart__count is-empty" data-webx-cart-count>0</span>~', $html);

        // The panel waits for the script: without it, it says so.
        $this->assertMatchesRegularExpression('~data-webx-cart-body\s*><noscript><p class="webx-cart__empty">Turn on JavaScript to see your cart.</p></noscript></div>~', $html);
        $this->assertStringNotContainsString('webx-cart__lines', $html);
        $this->assertStringNotContainsString(' open>', $html);
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('data-webx-cart-preview', $html);
    }

    #[Test]
    public function the_mini_cart_is_a_dropdown_beside_the_main_view(): void
    {
        // By default: the drawer, and the icon opens the mini cart under it.
        $html = Blade::render('<x-webx-cart />');
        $this->assertStringContainsString('class="webx-cart webx-cart--drawer webx-cart--preview"', $html);
        $this->assertStringContainsString('data-webx-cart-preview="dropdown"', $html);
        $this->assertStringContainsString('class="webx-dropdown webx-dropdown--bottom-end"', $html);
        $this->assertStringContainsString('<summary class="webx-dropdown__trigger webx-cart__trigger">', $html);
        $this->assertStringContainsString('data-webx-cart-body="preview"', $html);
        // The main view is there all the same: «Edit cart», a buy button and webx.cart.open() open it.
        $this->assertStringContainsString('data-webx-cart-panel="webx-cart"', $html);
        $this->assertStringContainsString('class="webx-dialog webx-dialog--sheet webx-dialog--drawer webx-cart__dialog"', $html);
        $this->assertStringNotContainsString('data-webx-dialog="webx-cart"', $html);

        $dialog = Blade::render('<x-webx-cart mode="dialog" />');
        $this->assertStringContainsString('class="webx-cart webx-cart--dialog webx-cart--preview"', $dialog);
        $this->assertStringContainsString('class="webx-dialog webx-dialog--sheet webx-cart__dialog"', $dialog);

        $this->app['config']->set('webx-cart.widget.mode', 'dialog');
        $this->app['config']->set('webx-cart.widget.preview', 'none');
        $configured = Blade::render('<x-webx-cart />');
        $this->assertStringContainsString('class="webx-cart webx-cart--dialog"', $configured);
        $this->assertStringNotContainsString('<details', $configured);
        $this->assertStringContainsString('data-webx-dialog="webx-cart"', $configured);
    }

    #[Test]
    public function an_unknown_mode_is_said_aloud(): void
    {
        // The dropdown is the mini cart now, not a mode of the main view.
        foreach ([
            '<x-webx-cart mode="sidebar" />' => 'mode="sidebar">: one of drawer, dialog.',
            '<x-webx-cart mode="dropdown" />' => 'mode="dropdown">: one of drawer, dialog.',
            '<x-webx-cart preview="popup" />' => 'preview="popup">: one of dropdown, none.',
        ] as $tag => $said) {
            try {
                Blade::render($tag);
                $this->fail($tag.' was printed.');
            } catch (Throwable $exception) {
                // Blade wraps what a component throws in a ViewException.
                $this->assertInstanceOf(ViewException::class, $exception);
                $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
                $this->assertStringContainsString($said, $exception->getMessage());
            }
        }
    }

    #[Test]
    public function the_counter_is_the_servers_and_the_script_comes_once(): void
    {
        $result = $this->app->make(CartService::class)->add(null, [['type' => 'test.product', 'ref' => 1, 'qty' => 3]], 'en');
        $this->withCartCookie((string) $result->token);

        $html = Blade::render('<x-webx-cart /><x-webx-cart::add type="test.product" :ref="2" /><x-webx-cart::add type="test.product" :ref="3" />');

        $this->assertMatchesRegularExpression('~<span class="webx-cart__count" data-webx-cart-count>3</span>~', $html);
        $this->assertSame(1, substr_count($html, '<script type="module" src="'.route('webx-cart.script', ['v' => Assets::version()]).'"></script>'));
    }

    #[Test]
    public function the_api_answers_the_panel_of_the_mode_the_widget_asks_for(): void
    {
        $token = $this->putItems([
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'parent' => 'set', 'qty' => 2],
            ['type' => 'test.product', 'ref' => 3],
        ], missing: true);

        $html = $this->panel($token, 'drawer');

        // The child under its parent, every sum the server's string, a stepper on a line of the root.
        $this->assertMatchesRegularExpression('~<li class="webx-cart__line" data-webx-cart-line="\d+">.*?Item A.*?<ul class="webx-cart__lines webx-cart__lines--children">\s*<li class="webx-cart__line" data-webx-cart-line="\d+">.*?Item B.*?</li>\s*</ul>\s*</li>~s', $html);
        $this->assertStringContainsString("49,99\u{00A0}€", $html);
        $this->assertStringContainsString("300,00\u{00A0}€", $html);
        $this->assertStringContainsString("829,99\u{00A0}€", $html);
        $this->assertSame(2, substr_count($html, 'class="webx-cart__stepper"'));
        $this->assertStringContainsString('data-webx-cart-remove=', $html);
        $this->assertStringContainsString('class="webx-cart__total-row webx-cart__payable"', $html);
        $this->assertStringNotContainsString('webx-cart__edit', $html);

        // The mini cart: the lines without steppers or remove buttons, and the way to the main view.
        $mini = $this->panel($token, 'dropdown');
        $this->assertStringContainsString('Item C', $mini);
        $this->assertStringNotContainsString('webx-cart__stepper', $mini);
        $this->assertStringNotContainsString('webx-cart__remove', $mini);
        $this->assertStringContainsString('<a class="webx-cart__edit" href="#webx-cart" data-webx-cart-edit>Edit cart</a>', $mini);

        // Both in one answer, as the widget with a mini cart asks.
        $both = $this->withCookie('webx_cart', $token)->getJson('/api/cart', ['X-Webx-Cart-View' => 'dialog', 'X-Webx-Cart-Preview' => 'dropdown'])->assertOk();
        $this->assertStringContainsString('webx-cart__stepper', (string) $both->json('html'));
        $this->assertStringContainsString('webx-cart__edit', (string) $both->json('preview_html'));

        // A mode nobody knows is no panel, not an error: the state is still the answer.
        $this->withCookie('webx_cart', $token)->getJson('/api/cart', ['X-Webx-Cart-View' => 'sidebar'])->assertOk()->assertJsonMissingPath('html');
        $this->withCookie('webx_cart', $token)->getJson('/api/cart', ['X-Webx-Cart-View' => 'dropdown'])->assertOk()->assertJsonMissingPath('html');
    }

    #[Test]
    public function a_gone_record_and_a_new_price_are_what_the_panel_shows(): void
    {
        $token = $this->putItems([
            ['type' => 'test.product', 'ref' => 1],
            ['type' => 'test.product', 'ref' => 3],
        ]);

        unset($this->products->lines[1]);
        $this->products->lines[3] = self::product('Item C', 45000);

        $html = $this->panel($token, 'dialog');

        $this->assertMatchesRegularExpression('~<li class="webx-cart__line is-unavailable"[^>]*>.*?<span class="webx-cart__line-name">Item A</span>.*?No longer available~s', $html);
        $this->assertStringContainsString("<s class=\"webx-cart__line-old\">480,00\u{00A0}€</s>", $html);
        $this->assertStringContainsString("450,00\u{00A0}€", $html);
        $this->assertStringContainsString('Item C is now cheaper.', $html);
    }

    #[Test]
    public function the_empty_cart_and_the_checkout_button(): void
    {
        $empty = $this->panel('', 'drawer');
        $this->assertStringContainsString('<p class="webx-cart__empty">Your cart is empty</p>', $empty);
        // Nothing to edit in an empty mini cart.
        $this->assertStringNotContainsString('webx-cart__edit', $this->panel('', 'dropdown'));

        $token = $this->putItems([['type' => 'test.product', 'ref' => 1]]);
        $this->assertStringNotContainsString('webx-cart__checkout', $this->panel($token, 'drawer'));

        $this->app['config']->set('webx-cart.checkout_url', '/order');
        $this->assertStringContainsString('<a class="webx-cart__checkout" href="/order">Checkout</a>', $this->panel($token, 'drawer'));

        // The checkout module's route, when the setting is empty.
        $this->app['config']->set('webx-cart.checkout_url', null);
        Route::get('/checkout', static fn (): string => 'checkout')->name('checkout');
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->assertStringContainsString('href="http://localhost/checkout"', $this->panel($token, 'drawer'));
    }

    #[Test]
    public function a_page_back_from_a_buy_button_without_javascript_prints_the_panel_open(): void
    {
        $result = $this->app->make(CartService::class)->add(null, [['type' => 'test.product', 'ref' => 2]], 'en');
        $this->withCartCookie((string) $result->token);
        $this->startSession();
        $this->app['session']->flash(CartController::FLASH, ['added' => $result->added]);
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $drawer = Blade::render('<x-webx-cart preview="none" />');
        $this->assertMatchesRegularExpression('~<dialog [^>]*class="webx-dialog webx-dialog--sheet webx-dialog--drawer webx-cart__dialog"[^>]*open\\s*>~', $drawer);
        $this->assertStringContainsString('data-webx-returned', $drawer);
        $this->assertStringContainsString('Item B', $drawer);
        $this->assertStringNotContainsString('<noscript>', $drawer);
        // Only the script sends a stepper or a remove button: without it they are not drawn.
        $this->assertStringNotContainsString('webx-cart__stepper', $drawer);
        $this->assertStringNotContainsString('webx-cart__remove', $drawer);

        // With the mini cart it is the main view that opens, not the dropdown.
        $dialog = Blade::render('<x-webx-cart mode="dialog" preview="dropdown" />');
        $this->assertMatchesRegularExpression('~<dialog [^>]*class="webx-dialog webx-dialog--sheet webx-cart__dialog"[^>]*open\\s*>~', $dialog);
        $this->assertDoesNotMatchRegularExpression('~<details [^>]*open~', $dialog);
    }

    #[Test]
    public function a_buy_at_the_maximum_opens_the_cart_with_a_notice_and_changes_nothing(): void
    {
        $this->app['config']->set('webx-cart.qty_max', 5);
        $token = $this->putItems([['type' => 'test.product', 'ref' => 1, 'qty' => 5]]);

        $response = $this->withCookie('webx_cart', $token)
            ->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1]]], ['X-Webx-Cart-View' => 'drawer', 'X-Webx-Cart-Preview' => 'dropdown'])
            ->assertUnprocessable()
            ->assertJsonPath('units', 5)
            ->assertJsonPath('lines.0.qty', 5);
        $this->assertSame(['The quantity is from 1 to 5.'], $response->json('errors')['items.0.qty']);

        // A notice on the line, where the stepper can take it down; no warning of the cart.
        $html = (string) $response->json('html');
        $this->assertMatchesRegularExpression('~data-webx-cart-line="\\d+">.*?<p class="webx-cart__notice">You already have the maximum quantity of this item.</p>.*?class="webx-cart__stepper"~s', $html);
        $this->assertStringNotContainsString('webx-cart__warning', $html);
        $this->assertStringContainsString('webx-cart__notice', (string) $response->json('preview_html'));

        // Without JavaScript: back to the page, the cart open with the same notice.
        $this->withCookie('webx_cart', $token)
            ->from('/product')
            ->post('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1]]])
            ->assertRedirect('/product')
            ->assertSessionHasNoErrors()
            ->assertSessionHas(CartController::FLASH);

        $this->withCartCookie($token);
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $page = Blade::render('<x-webx-cart />');
        $this->assertMatchesRegularExpression('~<dialog [^>]*open\\s*>~', $page);
        $this->assertStringContainsString('<p class="webx-cart__notice">You already have the maximum quantity of this item.</p>', $page);
    }

    #[Test]
    public function another_refusal_into_a_cart_is_said_in_the_panel_and_without_a_cart_only_as_errors(): void
    {
        $this->products->lines[2] = self::product('Item B', 15000, available: false);

        // No cart yet: the refusal alone, the button says it.
        $this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 2]]], ['X-Webx-Cart-View' => 'drawer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.ref')
            ->assertJsonMissingPath('lines')
            ->assertJsonMissingPath('html');

        $token = $this->putItems([['type' => 'test.product', 'ref' => 1]]);
        $response = $this->withCookie('webx_cart', $token)
            ->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 2]]], ['X-Webx-Cart-View' => 'drawer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.ref')
            ->assertJsonPath('units', 1);

        $this->assertMatchesRegularExpression('~<p class="webx-cart__warning">[^<]+</p>~', (string) $response->json('html'));
        $this->assertStringNotContainsString('webx-cart__notice', (string) $response->json('html'));

        // A form without JavaScript: back with the errors, as before.
        $this->withCookie('webx_cart', $token)
            ->from('/product')
            ->post('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 2]]])
            ->assertRedirect('/product')
            ->assertSessionHasErrors('items.0.ref');
    }

    #[Test]
    public function the_script_is_served_immutable_without_a_session(): void
    {
        $response = $this->get(route('webx-cart.script', ['v' => Assets::version()]))->assertOk();

        $this->assertStringContainsString('text/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('webx:cart:change', (string) $response->getContent());
        $response->assertCookieMissing('laravel_session');
    }

    /**
     * A batch through the API; the visitor's token for the next requests.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function putItems(array $items, bool $missing = false): string
    {
        $response = $this->postJson('/api/cart/items', ['items' => $items])->assertOk();

        if ($missing) {
            $response->assertJsonMissingPath('html');
        }

        return (string) $response->getCookie('webx_cart')?->getValue();
    }

    /** The panel's HTML the widget gets: of the main view in this mode, or the mini cart's (`dropdown`). */
    private function panel(string $token, string $mode): string
    {
        $request = $token === '' ? $this : $this->withCookie('webx_cart', $token);

        if ($mode === 'dropdown') {
            return (string) $request->getJson('/api/cart', ['X-Webx-Cart-Preview' => 'dropdown'])->assertOk()->assertJsonMissingPath('html')->json('preview_html');
        }

        return (string) $request->getJson('/api/cart', ['X-Webx-Cart-View' => $mode])->assertOk()->json('html');
    }
}
