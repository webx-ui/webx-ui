<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;
use WebxUi\Catalog\Tests\Fixtures\LandingRewriter;

/**
 * §7.7, §8.1 of the architecture and §16: a filter's address reads one way only, is written one
 * way only, and a rewriter is prepared once per render and takes the link over.
 */
final class FilterUrlsTest extends TestCase
{
    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();

        ColourFacet::migrate();
        $this->app->make(Facets::class)->register(new ColourFacet);

        $this->laptops = $this->category('laptops');
        ColourFacet::paint($this->product('Black one', $this->laptops, ['price' => 100]), 'black');
        ColourFacet::paint($this->product('White one', $this->laptops, ['price' => 300]), 'white');
        ColourFacet::paint($this->product('Red one', $this->laptops, ['price' => 900]), 'red');
    }

    #[Test]
    public function one_value_of_a_reference_book_is_an_open_page_with_its_own_title(): void
    {
        $this->get('/laptops/colour_black')
            ->assertOk()
            ->assertSee('Black one')
            ->assertDontSee('White one')
            ->assertSee('<title>Laptops Black</title>', false)
            ->assertDontSee('noindex', false);
    }

    #[Test]
    public function any_other_spelling_is_a_301_to_the_one_spelling(): void
    {
        // Values by slug, facets in the registry's order: category, price, then the satellites'.
        $this->get('/laptops/colour_white_black')->assertStatus(301)->assertRedirect('/laptops/colour_black_white');
        $this->get('/laptops/colour_black/price_100-500')->assertStatus(301)->assertRedirect('/laptops/price_100-500/colour_black');
        $this->get('/laptops/price_500-100')->assertStatus(301)->assertRedirect('/laptops/price_100-500');
        $this->get('/laptops/colour_black/colour_white')->assertStatus(301)->assertRedirect('/laptops/colour_black_white');

        // The query goes along: it is somebody's `?utm_…`.
        $this->get('/laptops/colour_white_black?utm_source=mail')->assertRedirect('/laptops/colour_black_white?utm_source=mail');
    }

    #[Test]
    public function a_tail_that_is_not_a_filter_of_this_page_is_a_404(): void
    {
        $this->get('/laptops/anything')->assertNotFound();
        $this->get('/laptops/colour_purple')->assertNotFound();
        $this->get('/laptops/brand_apple')->assertNotFound();
        $this->get('/laptops/colour_')->assertNotFound();
        $this->get('/laptops/price_cheap')->assertNotFound();
    }

    #[Test]
    public function combinations_several_values_and_ranges_are_closed(): void
    {
        $this->get('/laptops/colour_black_white')->assertOk()->assertSee('noindex, follow', false);
        $this->get('/laptops/price_100-500')->assertOk()->assertSee('noindex, follow', false);
        $this->get('/laptops/price_100-500/colour_black')->assertOk()->assertSee('noindex, follow', false);
    }

    #[Test]
    public function the_links_of_the_filter_say_nofollow_where_the_page_is_closed(): void
    {
        $page = $this->get('/laptops/colour_black')->assertOk()->getContent();

        // From "black" the next click on "white" is two values: closed.
        $this->assertStringContainsString('href="http://localhost/laptops/colour_black_white" rel="nofollow"', (string) $page);
        // Taking black off is the category itself: open.
        $this->assertMatchesRegularExpression('#href="http://localhost/laptops"(?! rel="nofollow")#', (string) $page);
    }

    #[Test]
    public function one_subcategory_chosen_on_a_category_page_is_that_subcategorys_page(): void
    {
        $gaming = $this->category('gaming-laptops', parent: $this->laptops);
        ColourFacet::paint($this->product('Gaming black', $gaming), 'black');

        $this->get('/laptops/category_gaming-laptops')->assertStatus(301)->assertRedirect('/gaming-laptops');
        $this->get('/laptops/category_gaming-laptops/colour_black')->assertStatus(301)->assertRedirect('/gaming-laptops/colour_black');

        // And the filter's own link to it goes there straight away.
        $this->get('/laptops')->assertOk()->assertSee('href="http://localhost/gaming-laptops"', false);
    }

    #[Test]
    public function a_chosen_ancestor_takes_its_chosen_descendants_in(): void
    {
        $gaming = $this->category('gaming-laptops', parent: $this->laptops);

        $context = new FilterContext('root', 'catalog', 'en', $this->app->make(Facets::class)->all());
        $state = FilterState::of(['category' => FacetValue::of([(string) $gaming->id, (string) $this->laptops->id])]);

        $this->assertSame('catalog/category_laptops', $this->app->make(FilterUrls::class)->path($context, $state));
    }

    #[Test]
    public function a_rewriter_is_prepared_once_per_render_and_takes_the_link_over(): void
    {
        $rewriter = new LandingRewriter('colour', 'black', 'laptops-black');
        $this->app->make(FilterUrls::class)->register($rewriter);

        $page = (string) $this->get('/laptops')->assertOk()->getContent();

        $this->assertSame(1, $rewriter->prepared);
        $this->assertStringContainsString('href="http://localhost/laptops-black"', $page);
        $this->assertStringNotContainsString('laptops/colour_black"', $page);

        // Choosing on top of the landing writes the rest after it (decision 26 of the architecture).
        $this->get('/laptops/colour_white')->assertOk()->assertSee('href="http://localhost/laptops-black/colour_white"', false);

        // An address from outside that the landing has taken over is a 301 to the landing.
        $this->get('/laptops/colour_black')->assertStatus(301)->assertRedirect('/laptops-black');
    }

    #[Test]
    public function the_range_form_without_a_script_lands_on_the_segment(): void
    {
        $this->get('/laptops/colour_black?range[price][from]=100&range[price][to]=500&sort=price_asc')
            ->assertStatus(302)
            ->assertRedirect('/laptops/price_100-500/colour_black?sort=price_asc');

        $this->get('/laptops/price_100-500?range[price][from]=&range[price][to]=')->assertRedirect('/laptops');
    }

    #[Test]
    public function a_hidden_category_is_a_404_with_a_filter_behind_it_too(): void
    {
        $this->laptops->update(['is_published' => false]);

        $this->get('/laptops')->assertNotFound();
        $this->get('/laptops/colour_black')->assertNotFound();
    }
}
