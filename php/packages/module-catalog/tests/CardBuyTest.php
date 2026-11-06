<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Purchase\PurchaseRule;
use WebxUi\Catalog\Purchase\Verdict;

/**
 * The buy button of every card (§5.3 of the collections' spec): one partial with the product
 * page, each answer of `Purchasability` a branch of it. Without the cart — {@see WithoutCartTest}.
 */
final class CardBuyTest extends TestCase
{
    use CountsQueries;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), CartServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The caches on, the way a site has them: what repeats then is the cards' own.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.cache.enabled', true);
        $app['config']->set('webx-seo.cache.enabled', true);
        $app['config']->set('webx-catalog.price.currency', 'EUR');
    }

    #[Test]
    public function a_product_that_can_be_bought_has_the_carts_button_outside_the_link(): void
    {
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);

        $card = $this->card($product);

        $this->assertStringContainsString('action="'.route('webx-cart.items.store').'"', $card);
        $this->assertStringContainsString('data-webx-cart-add="catalog.product:'.$product->id.'"', $card);
        $this->assertStringContainsString('webx-catalog-card__action', $card);
        $this->assertStringContainsString('>'.__('webx-catalog::storefront.buy').'</button>', $card);
        $this->assertStringNotContainsString('data-product=', $card);
        $this->assertGreaterThan(strrpos($card, '</a>'), strpos($card, '<form'));
    }

    #[Test]
    public function a_price_on_request_asks_the_price(): void
    {
        $product = $this->product('Item A', $this->category('tools'));

        $card = $this->card($product);

        $this->assertStringContainsString('class="webx-catalog-card__action" data-product="'.$product->id.'">'.__('webx-catalog::storefront.ask-price').'</button>', $card);
        $this->assertStringNotContainsString('<form', $card);
    }

    #[Test]
    public function a_refusal_says_its_words_in_place_of_a_button(): void
    {
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);
        $this->refuse('Sold by the metre');

        $card = $this->card($product);

        $this->assertStringContainsString('<p class="webx-catalog-card__refusal">Sold by the metre</p>', $card);
        $this->assertStringNotContainsString('<button', $card);
        $this->assertStringNotContainsString('<form', $card);
    }

    #[Test]
    public function the_product_page_keeps_its_own_classes_from_the_same_partial(): void
    {
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);
        $unpriced = $this->product('Item B', $this->category('hardware'));

        $this->get($product->url())->assertOk()->assertSee('webx-cart-add webx-catalog-product__action', false);
        $this->get($unpriced->url())->assertOk()->assertSee('<button type="button" class="webx-catalog-product__action" data-product="'.$unpriced->id.'">', false);

        $this->refuse('Sold by the metre');
        $this->get($product->url())->assertOk()->assertSee('<p class="webx-catalog-product__refusal">Sold by the metre</p>', false);
    }

    #[Test]
    public function the_cards_ask_nothing_per_product_with_a_cart_in_the_cookie(): void
    {
        $category = $this->category('tools');
        $products = [];

        foreach (range(1, 12) as $number) {
            $products[] = $this->product("Item {$number}", $category, ['price' => '49.99']);
        }

        $added = $this->post('/api/cart/items', ['items' => [['type' => 'catalog.product', 'ref' => (string) $products[0]->id, 'qty' => '1']]]);
        $cookie = (string) $added->getCookie('webx_cart')?->getValue();
        $page = fn () => $this->withCredentials()->withCookie('webx_cart', $cookie)->get('/tools')->assertOk();

        // The first pass fills the caches; a card that asks per product shows on the warm one.
        $first = (string) $page()->getContent();
        $this->assertSame(12, substr_count($first, 'data-webx-cart-add='));
        // The widget's script comes with the first button, not with each. Asked of the first pass:
        // Testbench keeps a scoped service between requests, so the next page has it printed already.
        $this->assertSame(1, substr_count($first, route('webx-cart.script', [], false)));

        $warm = $this->assertNoRepeatedQueries($page, 'the listing with the buttons');

        $this->assertSame(1, substr_count((string) $warm->getContent(), 'webx-cart-add is-in-cart webx-catalog-card__action'));
    }

    /** The one card of the listing, as printed. */
    private function card(Product $product): string
    {
        $content = (string) $this->get('/tools')->assertOk()->getContent();

        $this->assertSame(1, preg_match('~<article class="webx-catalog-card">.*?</article>~s', $content, $card), "No card of {$product->id}.");

        return $card[0];
    }

    /** A satellite's refusal of every product, as the stock's rule answers. */
    private function refuse(string $label): void
    {
        $this->app->make(Purchasability::class)->register(new class($label) implements PurchaseRule
        {
            public function __construct(private readonly string $label) {}

            public function refuse(Collection $products): array
            {
                return $products->mapWithKeys(fn (Product $product): array => [(int) $product->id => Verdict::no('by-the-metre', $this->label)])->all();
            }
        });
    }
}
