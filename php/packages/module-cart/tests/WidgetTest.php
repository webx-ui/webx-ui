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
 * The widget (§9): `<x-webx-cart>` in its three modes — the trigger with the server's counter and
 * an empty panel —, the panel's HTML in the API's answer when the widget asks for it, the panel
 * printed open after a buy button without JavaScript, the checkout button, the script once a page.
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
        $html = Blade::render('<x-webx-cart />');

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
    }

    #[Test]
    public function the_dialog_and_the_dropdown_are_the_other_two_modes(): void
    {
        $dialog = Blade::render('<x-webx-cart mode="dialog" />');
        $this->assertStringContainsString('class="webx-cart webx-cart--dialog"', $dialog);
        $this->assertStringContainsString('class="webx-dialog webx-dialog--sheet webx-cart__dialog"', $dialog);

        $dropdown = Blade::render('<x-webx-cart mode="dropdown" />');
        $this->assertStringContainsString('class="webx-cart webx-cart--dropdown"', $dropdown);
        $this->assertStringContainsString('class="webx-dropdown webx-dropdown--bottom-end"', $dropdown);
        $this->assertStringContainsString('<summary class="webx-dropdown__trigger webx-cart__trigger">', $dropdown);
        $this->assertStringNotContainsString('<dialog', $dropdown);
        $this->assertStringNotContainsString('data-webx-cart-panel', $dropdown);

        $this->app['config']->set('webx-cart.widget.mode', 'dropdown');
        $this->assertStringContainsString('webx-cart--dropdown', Blade::render('<x-webx-cart />'));
    }

    #[Test]
    public function an_unknown_mode_is_said_aloud(): void
    {
        try {
            Blade::render('<x-webx-cart mode="sidebar" />');
            $this->fail('A mode nobody knows was printed.');
        } catch (Throwable $exception) {
            // Blade wraps what a component throws in a ViewException.
            $this->assertInstanceOf(ViewException::class, $exception);
            $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
            $this->assertStringContainsString('one of drawer, dialog, dropdown', $exception->getMessage());
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

        // The mini cart: the lines without steppers or remove buttons.
        $mini = $this->panel($token, 'dropdown');
        $this->assertStringContainsString('Item C', $mini);
        $this->assertStringNotContainsString('webx-cart__stepper', $mini);
        $this->assertStringNotContainsString('webx-cart__remove', $mini);

        // A mode nobody knows is no panel, not an error: the state is still the answer.
        $this->withCookie('webx_cart', $token)->getJson('/api/cart', ['X-Webx-Cart-View' => 'sidebar'])->assertOk()->assertJsonMissingPath('html');
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

        $drawer = Blade::render('<x-webx-cart />');
        $this->assertMatchesRegularExpression('~<dialog [^>]*class="webx-dialog webx-dialog--sheet webx-dialog--drawer webx-cart__dialog"[^>]*open\\s*>~', $drawer);
        $this->assertStringContainsString('data-webx-returned', $drawer);
        $this->assertStringContainsString('Item B', $drawer);
        $this->assertStringNotContainsString('<noscript>', $drawer);
        // Only the script sends a stepper or a remove button: without it they are not drawn.
        $this->assertStringNotContainsString('webx-cart__stepper', $drawer);
        $this->assertStringNotContainsString('webx-cart__remove', $drawer);

        $dropdown = Blade::render('<x-webx-cart mode="dropdown" />');
        $this->assertMatchesRegularExpression('~<details [^>]*class="webx-dropdown webx-dropdown--bottom-end" open>~', $dropdown);
        $this->assertStringContainsString('Item B', $dropdown);
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

    /** The panel's HTML the widget of this mode gets. */
    private function panel(string $token, string $mode): string
    {
        $request = $token === '' ? $this : $this->withCookie('webx_cart', $token);

        return (string) $request->getJson('/api/cart', ['X-Webx-Cart-View' => $mode])->assertOk()->json('html');
    }
}
