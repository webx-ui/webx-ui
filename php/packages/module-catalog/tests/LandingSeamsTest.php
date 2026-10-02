<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Catalog\Events\FacetValueRetargeted;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterAliases;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Storefront\Storefront;
use WebxUi\Catalog\Storefront\StorefrontPart;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;
use WebxUi\Catalog\Tests\Fixtures\Landing;
use WebxUi\Catalog\Tests\Fixtures\LandingRewriter;

/**
 * §10 of the landings spec: what the core gives a landing before there is one — a page that
 * starts from a set and adds the reader's tail to it, a set a rewriter takes whole and opens, the
 * owner's texts, card and order, the two points of the list, and the event of a value gone.
 *
 * The landing here is the test rewriter and a route that calls the storefront the way the
 * landings' handler will.
 */
final class LandingSeamsTest extends TestCase
{
    private Category $laptops;

    private LandingRewriter $rewriter;

    protected function setUp(): void
    {
        parent::setUp();

        ColourFacet::migrate();
        $this->app->make(Facets::class)->register(new ColourFacet);

        $this->laptops = $this->category('laptops');
        ColourFacet::paint($this->product('Black cheap', $this->laptops, ['price' => 100]), 'black');
        ColourFacet::paint($this->product('Black dear', $this->laptops, ['price' => 900]), 'black');
        ColourFacet::paint($this->product('White one', $this->laptops, ['price' => 300]), 'white');

        $this->rewriter = new LandingRewriter;
        $this->app->make(FilterUrls::class)->register($this->rewriter);
    }

    #[Test]
    public function a_landing_lists_its_set_and_the_category_address_of_the_set_is_a_301_to_it(): void
    {
        $this->landing('laptops-black', ['colour' => FacetValue::of(['black'])]);

        $this->get('/laptops-black')
            ->assertOk()
            ->assertSee('Black cheap')
            ->assertSee('Black dear')
            ->assertDontSee('White one')
            // The way back from the set is the category.
            ->assertSee('href="http://localhost/laptops"', false);

        $this->get('/laptops/colour_black')->assertStatus(301)->assertRedirect('/laptops-black');
    }

    #[Test]
    public function a_choice_over_the_set_is_written_after_it_and_closed(): void
    {
        $this->landing('laptops-black', ['colour' => FacetValue::of(['black'])]);

        $this->get('/laptops-black/price_0-500')
            ->assertOk()
            ->assertSee('Black cheap')
            ->assertDontSee('Black dear')
            ->assertSee('noindex, follow', false);

        // The same state at the category's address is the landing's, with the rest after it.
        $this->get('/laptops/price_0-500/colour_black')->assertStatus(301)->assertRedirect('/laptops-black/price_0-500');
    }

    #[Test]
    public function the_largest_covering_landing_takes_the_state_and_taking_a_value_off_leads_to_the_smaller(): void
    {
        $this->landing('laptops-black', ['colour' => FacetValue::of(['black'])]);
        $this->landing('laptops-black-cheap', ['colour' => FacetValue::of(['black']), 'price' => FacetValue::range(0, 500)]);

        $this->get('/laptops-black/price_0-500')->assertStatus(301)->assertRedirect('/laptops-black-cheap');

        $this->get('/laptops-black-cheap')
            ->assertOk()
            // Price off: the smaller landing; colour off: the category with the price.
            ->assertSee('href="http://localhost/laptops-black"', false)
            ->assertSee('href="http://localhost/laptops/price_0-500"', false);
    }

    #[Test]
    public function a_landing_is_not_the_address_of_a_state_that_only_contains_its_set(): void
    {
        $this->landing('laptops-black', ['colour' => FacetValue::of(['black'])]);

        // Black and white is not "the black landing and white": one address, one meaning.
        $this->get('/laptops/colour_black_white')->assertOk()->assertSee('White one');
        $this->get('/laptops-black/colour_white')->assertStatus(301)->assertRedirect('/laptops/colour_black_white');
    }

    #[Test]
    public function a_set_taken_whole_is_open_when_the_rewriter_says_so_whatever_its_size(): void
    {
        $both = ['colour' => FacetValue::of(['black', 'white'])];

        // Two values of a facet are closed by the filter's own rules…
        $this->get('/laptops/colour_black_white')->assertOk()->assertSee('noindex, follow', false);

        // …and open as a landing that says it is.
        $this->landing('laptops-dark', $both);
        $this->get('/laptops-dark')->assertOk()->assertDontSee('noindex', false);
    }

    #[Test]
    public function a_set_taken_whole_without_the_flag_keeps_the_filters_rules(): void
    {
        $this->landing('laptops-dark', ['colour' => FacetValue::of(['black', 'white'])], indexable: false);

        $this->get('/laptops-dark')->assertOk()->assertSee('noindex, follow', false);
    }

    #[Test]
    public function the_plain_landing_prints_both_texts_and_a_choice_or_a_second_page_prints_none(): void
    {
        $this->app['config']->set('webx-catalog.per_page', 1);
        $this->landing('laptops-black', ['colour' => FacetValue::of(['black'])], new Landing(
            name: 'Black laptops',
            url: 'http://localhost/laptops-black',
            above: '<p>Above the list.</p>',
            below: '<p>Under the pages.</p>',
        ));

        $this->get('/laptops-black')->assertOk()->assertSee('Above the list.')->assertSee('Under the pages.');
        $this->get('/laptops-black?page=2')->assertOk()->assertDontSee('Above the list.')->assertDontSee('Under the pages.');
        $this->get('/laptops-black/price_0-500')->assertOk()->assertDontSee('Above the list.')->assertDontSee('Under the pages.');
    }

    #[Test]
    public function a_category_prints_its_description_above_on_the_plain_page_only(): void
    {
        $this->laptops->update(['description' => '<p>All the laptops.</p>']);

        $this->get('/laptops')->assertOk()->assertSee('<p>All the laptops.</p>', false);
        $this->get('/laptops/colour_white')->assertOk()->assertDontSee('All the laptops.');
    }

    #[Test]
    public function the_plain_landing_speaks_with_its_own_card_and_not_the_filters_title(): void
    {
        $this->landing('laptops-black', ['colour' => FacetValue::of(['black'])], new Landing(
            name: 'Black laptops',
            url: 'http://localhost/laptops-black',
            title: 'Black laptops to buy',
        ));

        // One value of one facet would be «Laptops Black» on the category; the landing is its own.
        $this->get('/laptops-black')
            ->assertOk()
            ->assertSee('<title>Black laptops to buy</title>', false)
            ->assertSee('<h1>Black laptops</h1>', false)
            ->assertSee('<link rel="canonical" href="http://localhost/laptops-black">', false)
            ->assertDontSee('Laptops Black');

        $this->get('/laptops-black/price_0-500')->assertOk()->assertSee('<h1>Black laptops</h1>', false)->assertDontSee('Black laptops to buy');
    }

    #[Test]
    public function the_owners_order_opens_the_list_and_the_readers_beats_it(): void
    {
        $this->landing('laptops-black', ['colour' => FacetValue::of(['black'])], new Landing(
            name: 'Black laptops',
            url: 'http://localhost/laptops-black',
            sort: 'price_desc',
        ));

        $plain = (string) $this->get('/laptops-black')->assertOk()->assertDontSee('noindex', false)->getContent();

        $this->assertLessThan(strpos($plain, 'Black cheap'), strpos($plain, 'Black dear'));
        // The catalogue's own default is now a choice of the reader's, so it carries the query.
        $this->assertStringContainsString('href="http://localhost/laptops-black?sort=default"', $plain);
        $this->assertStringNotContainsString('sort=price_desc', $plain);

        $sorted = (string) $this->get('/laptops-black?sort=price_asc')
            ->assertOk()
            ->assertSee('noindex, follow', false)
            ->assertSee('<link rel="canonical" href="http://localhost/laptops-black">', false)
            ->getContent();

        $this->assertLessThan(strpos($sorted, 'Black dear'), strpos($sorted, 'Black cheap'));
    }

    #[Test]
    public function the_two_points_of_the_list_print_the_satellites_parts_with_the_page(): void
    {
        View::addNamespace('fixture', __DIR__.'/Fixtures/views');

        foreach (['catalog.listing.top', 'catalog.listing.bottom'] as $point) {
            $this->app->make(StorefrontParts::class)->register(new class($point) implements StorefrontPart
            {
                public function __construct(private readonly string $point) {}

                public function point(): string
                {
                    return $this->point;
                }

                public function view(): string
                {
                    return 'fixture::listing-point';
                }

                public function prepare(Collection $products): array
                {
                    return ['point' => $this->point];
                }
            });
        }

        $this->landing('laptops-black', ['colour' => FacetValue::of(['black'])]);

        $page = (string) $this->get('/laptops-black')->assertOk()->getContent();
        $top = strpos($page, 'catalog.listing.top on laptops-black, plain');
        $bottom = strpos($page, 'catalog.listing.bottom on laptops-black, plain');

        $this->assertNotFalse($top);
        $this->assertNotFalse($bottom);
        $this->assertLessThan(strpos($page, 'Black cheap'), $top);
        $this->assertGreaterThan(strpos($page, 'Black cheap'), $bottom);

        $this->get('/laptops')->assertOk()->assertSee('catalog.listing.top on laptops, plain');
    }

    #[Test]
    public function a_value_merged_or_deleted_is_told_as_an_event_and_a_code_is_not(): void
    {
        Event::fake([FacetValueRetargeted::class]);
        $aliases = $this->app->make(FilterAliases::class);

        $aliases->retarget('p.1', FilterAliases::VALUE, '10', '11');
        $aliases->retarget('p.1', FilterAliases::VALUE, '12', null);
        $aliases->retarget('p.1', FilterAliases::CODE, 'old', 'new');

        Event::assertDispatchedTimes(FacetValueRetargeted::class, 2);
        Event::assertDispatched(FacetValueRetargeted::class, static fn (FacetValueRetargeted $event): bool => $event->facetKey === 'p.1' && $event->from === '10' && $event->to === '11');
        Event::assertDispatched(FacetValueRetargeted::class, static fn (FacetValueRetargeted $event): bool => $event->from === '12' && $event->to === null);
    }

    /**
     * A landing on the laptops: the rewriter knows its set, and its address calls the storefront
     * the way the landings' handler will — the category's context, the landing as the subject,
     * the set as the base.
     *
     * @param  array<string, FacetValue>  $set
     */
    private function landing(string $path, array $set, ?Landing $subject = null, bool $indexable = true): void
    {
        $this->rewriter->landing($set, $path, $indexable);
        $subject ??= new Landing(ucfirst(str_replace('-', ' ', $path)), 'http://localhost/'.$path);
        $laptops = $this->laptops;

        Route::get($path.'/{tail?}', function (Request $request, string $tail = '') use ($set, $subject, $laptops): Response {
            $context = new FilterContext(
                FilterContext::CATEGORY,
                'laptops',
                'en',
                $this->app->make(CategoryFacets::class)->visible($laptops),
                $laptops,
                [],
                $subject,
            );

            return $this->app->make(Storefront::class)->listing(
                $request,
                $context,
                $tail,
                'webx-catalog::category',
                [CategoryFacet::KEY => FacetValue::of([(string) $laptops->id])],
                (int) $laptops->id,
                base: FilterState::of($set),
            );
        })->where('tail', '.*');
    }
}
