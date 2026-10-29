<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Events\CartsMerging;
use WebxUi\Compositions\Compositions;
use WebxUi\Compositions\Inspector;
use WebxUi\Compositions\Testing\IsolatedCompositions;
use WebxUi\Orders\Checkout\Checkout;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\CheckoutLayout;
use WebxUi\Orders\Checkout\Http\ScriptController;
use WebxUi\Orders\Checkout\PlaceKeys;
use WebxUi\Orders\Checkout\Summary\CheckoutSummaryParts;
use WebxUi\Orders\Checkout\Summary\SummaryPart;

/**
 * The page as a composition (spec §27): the ids a theme patches by, `open` in the package and
 * `collapse` by a patch of `attrs`, the columns moved by a patch of `move`, the layout, the
 * script, the parts of the column a neighbour adds.
 */
final class CheckoutViewTest extends CheckoutTestCase
{
    use IsolatedCompositions;

    private ?string $theme = null;

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->isolateCompositions($app);
    }

    #[After]
    protected function forgetTheme(): void
    {
        if ($this->theme !== null) {
            (new Filesystem)->deleteDirectory($this->theme);
            $this->theme = null;
        }
    }

    #[Test]
    public function the_page_is_the_composition_with_the_ids_of_the_spec(): void
    {
        $tree = $this->app->make(Inspector::class)->tree('checkout.page');

        $this->assertSame(
            ['root', 'checkout', 'checkout.title', 'checkout.warnings', 'checkout.main', 'checkout.steps', 'checkout.aside', 'checkout.summary', 'checkout.summary.parts', 'checkout.trust', 'checkout.sticky'],
            array_map(static fn ($node): string => $node->id, iterator_to_array($tree->root->walk(), false)),
        );
        $this->assertSame('webx-orders::checkout.page', $tree->dispatcher);

        $html = $this->page();
        $this->assertStringContainsString('<div class="webx-orders-checkout" data-steps="open">', $html);
        $this->assertLessThan(strpos($html, 'webx-orders-checkout__aside'), strpos($html, 'webx-orders-checkout__main'));
        // The sticky row sends the column's form, without JavaScript too.
        $this->assertStringContainsString('<button type="submit" class="webx-form__submit webx-orders-checkout__sticky-place" form="checkout-place">Place the order</button>', $html);
        $this->assertSame([], $this->app->make(Compositions::class)->findings());
    }

    #[Test]
    public function a_theme_folds_the_steps_and_moves_the_columns_by_patches(): void
    {
        $this->theme([
            'attrs' => ['checkout' => ['data-steps' => 'collapse']],
            'move' => [['id' => 'checkout.aside', 'before' => 'checkout.main']],
        ]);

        $html = $this->page();

        $this->assertStringContainsString('<div class="webx-orders-checkout" data-steps="collapse">', $html);
        $this->assertLessThan(strpos($html, 'webx-orders-checkout__main'), strpos($html, 'webx-orders-checkout__aside'));
    }

    /** theme-default carries `collapse` (§27.1): the patch is the one the spec names. */
    #[Test]
    public function the_default_theme_folds_the_steps(): void
    {
        $patch = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/theme-default/compositions/checkout.page/default.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['checkout' => ['data-steps' => 'collapse']], $patch['attrs']);
        $this->assertSame(['$schema', '$comment', 'attrs'], array_keys($patch));
    }

    #[Test]
    public function the_layout_is_the_minimal_one_of_the_theme_or_the_sites_choice(): void
    {
        // No theme here: the layout of the buyer's page, else the package's document.
        $this->assertSame(CheckoutLayout::STANDALONE, CheckoutLayout::name());
        config()->set('webx-orders.layout', 'site::layout');
        $this->assertSame('site::layout', CheckoutLayout::name());

        $views = sys_get_temp_dir().'/webx-orders-views-'.str_replace('.', '', uniqid('', true));
        mkdir($views.'/components/layout', 0777, true);
        file_put_contents($views.'/components/layout/minimal.blade.php', '<html><head>{{ $head ?? \'\' }}</head><body data-layout="minimal" data-handle="{{ $attributes->get(\'handle\') }}">{{ $slot }}@stack(\'scripts\')</body></html>');
        View::addLocation($views);

        try {
            $this->assertSame(CheckoutLayout::MINIMAL, CheckoutLayout::name());

            $html = $this->page();
            $this->assertStringContainsString('<body data-layout="minimal" data-handle="checkout.page">', $html);
            $this->assertMatchesRegularExpression('~<script type="module" src="http://localhost/webx/orders/checkout\.js\?v=[0-9a-f]{16}"></script>\s*</body>~', $html);

            // The site's own choice is stronger: the whole layout back.
            config()->set('webx-orders.checkout.layout', 'site::layout');
            $this->assertSame('site::layout', CheckoutLayout::name());
        } finally {
            (new Filesystem)->deleteDirectory($views);
        }
    }

    #[Test]
    public function the_script_is_served_for_good_and_small(): void
    {
        $response = $this->get('/webx/orders/checkout.js?v='.ScriptController::version())->assertOk();

        $this->assertStringStartsWith('text/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('webx:users:signed-in', (string) $response->getContent());
        $this->assertLessThan(4096, strlen((string) gzencode(ScriptController::contents(), 9)), 'checkout.js is small (§27.3)');
    }

    #[Test]
    public function a_part_of_a_neighbour_stands_in_the_column_and_one_that_throws_is_left_out(): void
    {
        $parts = $this->app->make(CheckoutSummaryParts::class);
        $parts->register('wallet', $this->part('<p>Pay with the wallet</p>'), 20);
        $parts->register('coupons', $this->part('<p>Promo code</p>'), 10);
        $parts->register('broken', $this->part(null), 5);
        $parts->register('elsewhere', $this->part('<p>Never</p>', false), 1);

        $html = $this->page();

        $this->assertStringContainsString('<div class="webx-orders-checkout__part" data-part="coupons"><p>Promo code</p></div>', $html);
        $this->assertLessThan(strpos($html, 'Pay with the wallet'), strpos($html, 'Promo code'));
        $this->assertStringNotContainsString('Never', $html);
        $this->assertStringNotContainsString('data-part="broken"', $html);
    }

    /** At the sign-in the cart under the page is another one (§25): the next column has a new key. */
    #[Test]
    public function the_carts_joining_at_a_sign_in_make_a_new_key(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $before = $this->hidden($cart)['place_key'];
        $this->assertSame($before, $this->hidden($cart)['place_key']);

        // The account's cart has a draft of its own: the guest's stays where it is, the key goes all the same.
        $account = $this->cart();
        $this->fill($account);
        Checkout::moveDraft(new CartsMerging((int) $cart->cart->id, (int) $account->cart->id));

        $this->assertNull($this->app['session.store']->get(PlaceKeys::SESSION));
    }

    private function page(): string
    {
        $cart = $this->cart();

        return (string) $this->visit($cart, '/checkout')->assertOk()->getContent();
    }

    /** @param  array<string, mixed>  $patch */
    private function theme(array $patch): void
    {
        $this->theme = sys_get_temp_dir().'/webx-orders-theme-'.str_replace('.', '', uniqid('', true));
        mkdir($this->theme.'/compositions/checkout.page', 0777, true);
        file_put_contents($this->theme.'/compositions/checkout.page/default.json', json_encode($patch, JSON_THROW_ON_ERROR));

        $compositions = $this->theme.'/compositions';
        Compositions::themes(static fn (): array => [['name' => 'site', 'dir' => $compositions, 'local' => true]]);
    }

    private function part(?string $html, bool $applies = true): SummaryPart
    {
        return new class($html, $applies) implements SummaryPart
        {
            public function __construct(private ?string $html, private bool $applies) {}

            public function applies(CheckoutContext $context): bool
            {
                return $this->applies;
            }

            public function render(CheckoutContext $context): Htmlable
            {
                return new HtmlString($this->html ?? throw new \RuntimeException('down'));
            }
        };
    }
}
