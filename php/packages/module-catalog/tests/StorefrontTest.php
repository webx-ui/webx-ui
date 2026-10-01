<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Popularity\ViewCounter;
use WebxUi\Catalog\Storefront\StorefrontPart;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;

/**
 * §10: the pages of the storefront — what they print, what their `<head>` says, what a
 * satellite puts into them — and the root and the search.
 */
final class StorefrontTest extends TestCase
{
    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laptops = $this->category('laptops');
    }

    #[Test]
    public function a_sorted_page_is_closed_with_its_canonical_on_the_unsorted_one(): void
    {
        $this->product('One', $this->laptops, ['price' => 10]);

        $this->get('/laptops?sort=price_asc')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="http://localhost/laptops">', false);

        $this->get('/laptops')->assertOk()->assertDontSee('noindex', false);
    }

    #[Test]
    public function a_page_past_the_first_is_closed_and_a_page_past_the_last_is_a_404(): void
    {
        $this->app['config']->set('webx-catalog.per_page', 1);
        $this->product('One', $this->laptops);
        $this->product('Two', $this->laptops);

        $this->get('/laptops?page=2')
            ->assertOk()
            ->assertSee('noindex, follow', false)
            ->assertSee('<link rel="canonical" href="http://localhost/laptops">', false);

        $this->get('/laptops?page=3')->assertNotFound();
    }

    #[Test]
    public function a_category_lists_its_products_as_an_item_list(): void
    {
        $product = $this->product('ThinkPad', $this->laptops);

        $this->get('/laptops')
            ->assertOk()
            ->assertSee('"@type":"ItemList"', false)
            ->assertSee("thinkpad-{$product->id}", false);
    }

    #[Test]
    public function the_product_page_offers_to_buy_and_describes_the_product_for_search_engines(): void
    {
        $this->app['config']->set('webx-catalog.price.currency', 'eur');
        $priced = $this->product('ThinkPad', $this->laptops, ['price' => 999, 'sku' => 'TP-1']);
        $unpriced = $this->product('Prototype', $this->laptops);

        $this->get("/thinkpad-{$priced->id}")
            ->assertOk()
            ->assertSee('Buy')
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"priceCurrency":"EUR"', false)
            ->assertSee('"sku":"TP-1"', false);

        $this->get("/prototype-{$unpriced->id}")
            ->assertOk()
            ->assertSee('Ask the price')
            ->assertDontSee('"@type":"Offer"', false);
    }

    #[Test]
    public function without_a_currency_there_is_no_offer(): void
    {
        $product = $this->product('ThinkPad', $this->laptops, ['price' => 999]);

        $this->get("/thinkpad-{$product->id}")
            ->assertOk()
            ->assertSee('"@type":"Product"', false)
            ->assertDontSee('"@type":"Offer"', false);
    }

    #[Test]
    public function a_view_of_the_product_page_is_counted_and_a_crawlers_is_not(): void
    {
        $product = $this->product('ThinkPad', $this->laptops);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Firefox/140.0')->get("/thinkpad-{$product->id}")->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get("/thinkpad-{$product->id}")->assertOk();

        $this->assertSame([$product->id => 1], $this->app->make(ViewCounter::class)->take());
    }

    #[Test]
    public function a_satellite_puts_its_part_into_every_card_prepared_once_per_page(): void
    {
        View::addNamespace('fixture', __DIR__.'/Fixtures/views');

        $part = new class implements StorefrontPart
        {
            public int $prepared = 0;

            public function point(): string
            {
                return 'catalog.card.badges';
            }

            public function view(): string
            {
                return 'fixture::badge';
            }

            public function prepare(Collection $products): array
            {
                $this->prepared++;

                return ['sale' => $products->modelKeys()];
            }
        };

        $this->app->make(StorefrontParts::class)->register($part);

        $a = $this->product('One', $this->laptops);
        $b = $this->product('Two', $this->laptops);

        $page = (string) $this->get('/laptops')->assertOk()->getContent();

        $this->assertSame(1, $part->prepared);
        $this->assertStringContainsString("badge-{$a->id}", $page);
        $this->assertStringContainsString("badge-{$b->id}", $page);
    }

    #[Test]
    public function the_search_finds_by_name_and_is_never_indexed(): void
    {
        $this->product('ThinkPad X1', $this->laptops);
        $this->product('MacBook', $this->laptops);

        $this->get('/catalog/search?q=thinkpad')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee('ThinkPad X1')
            ->assertDontSee('MacBook');

        $this->get('/catalog/search')->assertOk()->assertSee('noindex, follow', false);
    }

    #[Test]
    public function a_search_that_is_the_code_of_one_product_goes_to_its_card(): void
    {
        $plug = $this->product('Spark plug', $this->laptops, ['sku' => 'AT-1234/56', 'slug' => 'spark-plug']);
        $this->product('Adapter for AT-1234/56', $this->laptops);

        $this->get('/catalog/search?q='.urlencode('AT-1234/56'))->assertStatus(302)->assertRedirect($plug->url());

        // Two products with one barcode: the list, not a guess.
        $this->product('Coil', $this->laptops, ['barcode' => '4601234567890']);
        $this->product('Coil, the other', $this->laptops, ['barcode' => '4601234567890']);
        $this->get('/catalog/search?q=4601234567890')->assertOk()->assertSee('Coil, the other');

        // Not on the site: no card to go to.
        $this->product('Hidden plug', $this->laptops, ['sku' => 'NGK-1', 'is_published' => false]);
        $this->get('/catalog/search?q=NGK-1')->assertOk()->assertDontSee('Hidden plug');
    }

    #[Test]
    public function the_root_of_the_catalogue_is_off_by_default(): void
    {
        $this->get('/catalog')->assertNotFound();
    }

    #[Test]
    #[DefineEnvironment('withRoot')]
    public function the_root_filters_the_whole_catalogue_by_category(): void
    {
        $phones = $this->category('phones');
        $this->product('ThinkPad', $this->laptops);
        $this->product('Pixel', $phones);

        $this->get('/catalog')->assertOk()->assertSee('ThinkPad')->assertSee('Pixel')
            ->assertSee('href="http://localhost/catalog/category_phones"', false);

        // A category is a filter here, and one of them is open to the index.
        $this->get('/catalog/category_phones')->assertOk()->assertSee('Pixel')->assertDontSee('ThinkPad')
            ->assertDontSee('noindex', false);

        $this->get('/catalog/category_phones_laptops')->assertStatus(301)->assertRedirect('/catalog/category_laptops_phones');
        $this->get('/catalog/search?q=pixel')->assertOk()->assertSee('Pixel');
    }

    #[Test]
    public function the_sitemap_lists_first_levels_that_are_not_empty(): void
    {
        ColourFacet::migrate();
        $this->app->make(Facets::class)->register(new ColourFacet);

        ColourFacet::paint($this->product('Black one', $this->laptops), 'black');
        ColourFacet::paint($this->product('Nowhere', null), 'white');

        $map = (string) $this->get('/sitemap-catalog-filters.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/laptops/colour_black<', $map);
        // White is only on a product that is not on the site; a range is never a page.
        $this->assertStringNotContainsString('colour_white', $map);
        $this->assertStringNotContainsString('price_', $map);
    }

    /**
     * @param  Application  $app
     */
    protected function withRoot($app): void
    {
        $app['config']->set('webx-catalog.root.enabled', true);
    }
}
