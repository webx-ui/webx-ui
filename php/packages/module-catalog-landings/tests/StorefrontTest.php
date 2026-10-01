<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\Seo\Sitemap\Sitemap;

/**
 * §5–§6 of the landings spec on a real site: the address of a set, the page, its card, its texts,
 * its order and its strip.
 */
final class StorefrontTest extends TestCase
{
    private Category $laptops;

    private Brand $apple;

    private Brand $dell;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laptops = $this->category('laptops');
        $this->apple = $this->brand('Apple');
        $this->dell = $this->brand('Dell');

        $this->product('MacBook Air', $this->laptops, $this->apple, ['price' => 900]);
        $this->product('MacBook Pro', $this->laptops, $this->apple, ['price' => 2000]);
        $this->product('XPS', $this->laptops, $this->dell, ['price' => 1200]);
    }

    #[Test]
    public function a_landing_lists_its_set_and_the_category_spelling_of_it_is_a_301(): void
    {
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);

        $this->get('/laptops-apple')
            ->assertOk()
            ->assertSee('MacBook Air')
            ->assertSee('MacBook Pro')
            ->assertDontSee('XPS')
            ->assertDontSee('noindex', false)
            ->assertSee('<link rel="canonical" href="http://localhost/laptops-apple">', false);

        $this->get('/laptops/brand_apple')->assertStatus(301)->assertRedirect('/laptops-apple');

        // The category's filter links to the landing straight away.
        $this->get('/laptops')->assertOk()->assertSee('href="http://localhost/laptops-apple"', false);
    }

    #[Test]
    public function a_choice_over_the_set_is_written_after_it_closed_and_taking_it_off_leads_back(): void
    {
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);

        $this->get('/laptops-apple/price_0-1000')
            ->assertOk()
            ->assertSee('MacBook Air')
            ->assertDontSee('MacBook Pro')
            ->assertSee('noindex, follow', false);

        $this->get('/laptops/brand_apple/price_0-1000')->assertStatus(301)->assertRedirect('/laptops-apple/price_0-1000');
    }

    #[Test]
    public function the_largest_covering_landing_wins_and_a_value_taken_off_leads_to_the_smaller(): void
    {
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);
        $this->landing('cheap-apple', $this->laptops, ['brand' => $this->brands([$this->apple]), 'price' => ['min' => 0, 'max' => 1000]]);

        $this->get('/laptops-apple/price_0-1000')->assertStatus(301)->assertRedirect('/cheap-apple');

        $this->get('/cheap-apple')
            ->assertOk()
            ->assertSee('href="http://localhost/laptops-apple"', false)
            ->assertSee('href="http://localhost/laptops/price_0-1000"', false);
    }

    #[Test]
    public function apple_and_dell_are_not_the_apple_landing(): void
    {
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);

        $this->get('/laptops/brand_apple_dell')->assertOk()->assertSee('XPS');
        $this->get('/laptops-apple/brand_dell')->assertStatus(301)->assertRedirect('/laptops/brand_apple_dell');
    }

    #[Test]
    public function a_subcategory_chosen_on_a_landing_leads_to_its_landing_or_to_its_filter(): void
    {
        $gaming = $this->category('gaming', $this->laptops);
        $this->product('Alienware', $gaming, $this->dell);
        $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])]);

        // No landing below: the subcategory's own address with the set as its filter.
        $this->get('/laptops-dell')->assertOk()->assertSee('href="http://localhost/gaming/brand_dell"', false);

        $this->landing('gaming-dell', $gaming, ['brand' => $this->brands([$this->dell])]);
        $this->get('/laptops-dell')->assertOk()->assertSee('href="http://localhost/gaming-dell"', false);
    }

    #[Test]
    public function a_landing_on_the_whole_catalogue_takes_the_roots_set(): void
    {
        $this->landing('apple', null, ['brand' => $this->brands([$this->apple])]);

        $this->get('/apple')->assertOk()->assertSee('MacBook Air')->assertDontSee('XPS');
        $this->get('/apple/price_0-1000')->assertOk()->assertSee('MacBook Air')->assertDontSee('MacBook Pro')->assertSee('noindex, follow', false);
        // A category's set is not the root's.
        $this->get('/laptops/brand_apple')->assertOk();
    }

    #[Test]
    public function a_hidden_landing_or_one_on_a_hidden_base_is_a_404(): void
    {
        $landing = $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);

        $landing->update(['is_published' => false]);
        $this->get('/laptops-apple')->assertNotFound();
        // …and the category's address of the set is the category's again.
        $this->get('/laptops/brand_apple')->assertOk();

        $landing->update(['is_published' => true]);
        $this->laptops->update(['is_published' => false]);
        $this->get('/laptops-apple')->assertNotFound();

        $this->laptops->update(['is_published' => true]);
        $this->get('/laptops-apple')->assertOk();
    }

    #[Test]
    public function the_plain_landing_speaks_with_its_card_and_an_empty_one_is_closed_and_out_of_the_sitemap(): void
    {
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])], ['h1' => 'Apple laptops']);

        $this->get('/laptops-apple')
            ->assertOk()
            ->assertSee('<title>Apple laptops</title>', false)
            ->assertSee('<h1>Apple laptops</h1>', false);

        $nobody = $this->brand('Nobody');
        $empty = $this->landing('laptops-nobody', $this->laptops, ['brand' => $this->brands([$nobody])]);
        $this->artisan('webx:catalog-landings:count', ['--all' => true])->assertSuccessful();

        $this->assertSame(0, $empty->refresh()->products_count);
        $this->get('/laptops-nobody')->assertOk()->assertSee('noindex', false);

        $sitemap = $this->app->make(Sitemap::class);
        $this->assertTrue($sitemap->verdict('http://localhost/laptops-apple')['included']);
        $this->assertSame(['included' => false, 'reason' => 'noindex'], $sitemap->verdict('http://localhost/laptops-nobody'));
    }

    #[Test]
    public function the_plain_landing_prints_both_texts_and_a_choice_or_a_second_page_none(): void
    {
        $this->app['config']->set('webx-catalog.per_page', 1);
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])], [
            'text_above' => '<p>Above the list.</p>',
            'text_below' => '<p>Under the pages.</p>',
        ]);

        $this->get('/laptops-apple')->assertOk()->assertSee('Above the list.')->assertSee('Under the pages.');
        $this->get('/laptops-apple?page=2')->assertOk()->assertDontSee('Above the list.')->assertDontSee('Under the pages.');
        $this->get('/laptops-apple/price_0-1000')->assertOk()->assertDontSee('Above the list.')->assertDontSee('Under the pages.');
    }

    #[Test]
    public function the_landings_order_opens_the_list_the_readers_beats_it_and_the_canonical_stays_clean(): void
    {
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])], ['sort' => 'price_desc']);

        $plain = (string) $this->get('/laptops-apple')->assertOk()->assertDontSee('noindex', false)->getContent();
        $this->assertLessThan(strpos($plain, 'MacBook Air'), strpos($plain, 'MacBook Pro'));

        $sorted = (string) $this->get('/laptops-apple?sort=price_asc')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/laptops-apple">', false)
            ->getContent();
        $this->assertLessThan(strpos($sorted, 'MacBook Pro'), strpos($sorted, 'MacBook Air'));
    }

    #[Test]
    public function the_recommended_strip_skips_a_product_off_the_site_and_takes_it_back(): void
    {
        $landing = $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);
        $pick = $this->product('Studio Display', $this->laptops, null, ['price' => 1500]);
        $landing->recommended()->sync([$pick->id => ['position' => 1]]);

        $this->get('/laptops-apple')->assertOk()->assertSee('Recommended')->assertSee('Studio Display');
        // Over the plain page only.
        $this->get('/laptops-apple/price_0-1000')->assertOk()->assertDontSee('Studio Display');

        $pick->update(['is_published' => false]);
        $this->get('/laptops-apple')->assertOk()->assertDontSee('Studio Display');
        $this->assertSame(1, $landing->recommended()->count());

        $pick->update(['is_published' => true]);
        $this->get('/laptops-apple')->assertOk()->assertSee('Studio Display');
    }

    #[Test]
    public function a_landing_in_the_bin_gives_its_address_back_to_the_category(): void
    {
        $landing = $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);
        $landing->delete();

        $this->get('/laptops/brand_apple')->assertOk();
        $this->assertSoftDeleted(Landing::class, ['id' => $landing->id]);
    }
}
