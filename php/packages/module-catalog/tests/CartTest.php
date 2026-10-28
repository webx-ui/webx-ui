<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\PurchasableLine;
use WebxUi\Cart\Purchasables;
use WebxUi\Catalog\Cart\ProductPurchasable;
use WebxUi\Catalog\Models\Product;

/**
 * The catalogue as the cart's first provider (§7 of the cart's spec): `catalog.product` lines
 * priced live from the product, a batch for the price of one, and the product page's buy button
 * that puts it into the cart — a form, so without JavaScript too.
 */
final class CartTest extends TestCase
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

        $app['config']->set('webx-catalog.price.currency', 'EUR');
    }

    /**
     * @param  Application  $app
     */
    protected function pricesOff($app): void
    {
        $app['config']->set('webx-catalog.price.enabled', false);
    }

    #[Test]
    public function a_product_is_a_line_with_its_price_in_minor_units_and_its_names(): void
    {
        $this->useLocales('en', 'pl');
        $product = $this->product('Item A', $this->category('tools'), ['price' => '1234.50', 'sku' => 'A-100']);
        $product->setTranslation('name', 'pl', 'Rzecz A')->save();

        $line = $this->resolve([$product->id])[$product->id];

        $this->assertInstanceOf(PurchasableLine::class, $line);
        $this->assertSame('Item A', $line->name);
        $this->assertSame('A-100', $line->sku);
        $this->assertSame(123450, $line->price);
        $this->assertSame('EUR', $line->currency);
        $this->assertSame($product->url('en'), $line->url);
        $this->assertNull($line->maxQty);
        $this->assertTrue($line->available);
        $this->assertSame(['en' => 'Item A', 'pl' => 'Rzecz A'], $line->names);
        $this->assertSame('Rzecz A', $this->resolve([$product->id], 'pl')[$product->id]?->name);
    }

    #[Test]
    public function deleted_is_gone_and_hidden_or_without_a_price_cannot_be_bought(): void
    {
        $tools = $this->category('tools');
        $deleted = $this->product('Deleted', $tools, ['price' => '10.00']);
        $deleted->delete();
        $hidden = $this->product('Hidden', null, ['price' => '10.00']);
        $onRequest = $this->product('On request', $tools);
        $free = $this->product('Free', $tools, ['price' => '0.00']);

        $lines = $this->resolve([$deleted->id, $hidden->id, $onRequest->id, $free->id]);

        $this->assertArrayNotHasKey($deleted->id, $lines);
        $this->assertFalse($lines[$hidden->id]?->available);
        $this->assertSame('Hidden', $lines[$hidden->id]->name);
        $this->assertFalse($lines[$onRequest->id]?->available);
        $this->assertSame(0, $lines[$onRequest->id]->price);
        $this->assertFalse($lines[$free->id]?->available);
    }

    #[Test]
    public function a_batch_of_thirty_costs_what_one_does(): void
    {
        $tools = $this->category('tools');
        $ids = [];

        for ($i = 1; $i <= 30; $i++) {
            $ids[] = (int) $this->product("Item {$i}", $tools, ['price' => "{$i}.99"])->id;
        }

        $one = $this->recordQueries(fn () => $this->resolve([$ids[0]]))->count();
        $this->resolve([$ids[0]]);

        $this->assertQueriesWithin($one, fn () => $this->resolve($ids), 'thirty products');
        $this->assertNoRepeatedQueries(fn () => $this->resolve($ids), 'thirty products');
    }

    #[Test]
    #[DefineEnvironment('pricesOff')]
    public function with_prices_off_the_catalogue_sells_nothing(): void
    {
        $this->assertFalse($this->app->make(Purchasables::class)->has('catalog.product'));

        $product = $this->product('Item A', $this->category('tools'));

        $this->get($product->url())->assertOk()->assertDontSee('webx-cart-add', false);
    }

    #[Test]
    public function the_buy_button_puts_the_product_into_the_cart_without_javascript(): void
    {
        $product = $this->product('Item A', $this->category('tools'), ['price' => '49.99']);
        $page = $product->url();

        $this->get($page)
            ->assertOk()
            ->assertSee('action="'.route('webx-cart.items.store').'"', false)
            ->assertSee('name="items[0][type]" value="catalog.product"', false)
            ->assertSee('data-webx-cart-add="catalog.product:'.$product->id.'"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('data-product=', false);

        // What the form sends, as a browser without JavaScript would.
        $response = $this->from($page)->post('/api/cart/items', ['items' => [['type' => 'catalog.product', 'ref' => (string) $product->id, 'qty' => '1']]])
            ->assertRedirect($page);

        $this->assertSame(4999, CartItem::query()->value('price_at_add'));

        // The cart shows the product's price now, not the one it went in at.
        Product::query()->whereKey($product->id)->update(['price' => '39.99']);

        $this->withCredentials()
            ->withCookie('webx_cart', (string) $response->getCookie('webx_cart')?->getValue())
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('lines.0.type', 'catalog.product')
            ->assertJsonPath('lines.0.name', 'Item A')
            ->assertJsonPath('lines.0.price', 3999)
            ->assertJsonPath('lines.0.price_formatted', "39,99\u{00A0}€")
            ->assertJsonPath('lines.0.url', $page)
            ->assertJsonPath('total', 3999);

        $this->get($page)->assertSee('webx-cart-add is-in-cart', false);
    }

    #[Test]
    public function a_product_that_cannot_be_bought_gets_no_cart_button(): void
    {
        $product = $this->product('On request', $this->category('tools'));

        $this->get($product->url())->assertOk()->assertDontSee('webx-cart-add', false);
        $this->postJson('/api/cart/items', ['items' => [['type' => 'catalog.product', 'ref' => $product->id]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.ref']);
    }

    /**
     * @param  list<int>  $refs
     * @return array<int, PurchasableLine|null>
     */
    private function resolve(array $refs, string $locale = 'en'): array
    {
        $this->app->forgetScopedInstances();

        return $this->app->make(ProductPurchasable::class)->resolve($refs, $locale);
    }
}
