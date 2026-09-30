<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\FacetRelevance;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\PriceFacet;
use WebxUi\Catalog\Filter\FilterAliases;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterSerializer;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Search\SearchContributors;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;
use WebxUi\Catalog\Tests\Fixtures\PropertySource;

/**
 * §4 and §5.2 of the properties spec, the core's half: a facet's code in each language, the old
 * spellings of the filter's addresses, facets from the database, the facets a page keeps and the
 * ones under «More filters», the unchosen ones counted in one query, and the search seam.
 */
final class FacetSeamsTest extends TestCase
{
    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();

        ColourFacet::migrate();
        PropertySource::migrate();
        $this->app->make(Facets::class)->register(new ColourFacet);
        $this->laptops = $this->category('laptops');
    }

    #[Test]
    public function a_facet_code_is_the_one_of_the_page_language_and_the_key_elsewhere(): void
    {
        $this->app['config']->set('webx-catalog.facet_codes', ['price' => ['ru' => 'cena']]);
        $facets = $this->app->make(Facets::class);
        $serializer = $this->app->make(FilterSerializer::class);
        $price = $facets->find(PriceFacet::KEY);

        $this->assertSame('cena', $price?->code('ru'));
        $this->assertSame('price', $price->code('en'));
        $this->assertSame($price, $facets->byCode('cena', 'ru'));
        $this->assertNull($facets->byCode('cena', 'en'));

        $ru = new FilterContext(FilterContext::CATEGORY, 'noutbuki', 'ru', $facets->all(), $this->laptops);
        $state = FilterState::of([PriceFacet::KEY => FacetValue::range(100, 500)]);

        $this->assertSame(['noutbuki/cena_100-500'], $serializer->buildMany($ru, [$state]));
        $this->assertTrue($serializer->parse($ru, 'cena_100-500')?->get(PriceFacet::KEY)?->equals(FacetValue::range(100, 500)));
        $this->assertNull($serializer->parse($ru, 'price_100-500'));
    }

    #[Test]
    public function a_code_taken_in_one_language_only_is_refused_at_registration(): void
    {
        $this->app['config']->set('webx-catalog.facet_codes', ['price' => ['ru' => 'cvet'], 'colour' => ['ru' => 'cvet']]);
        $facets = $this->app->make(Facets::class);
        $facets->forget('colour');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The code [cvet] of the facet [colour] in [ru] is taken by [price].');

        $facets->register(new ColourFacet);
    }

    #[Test]
    public function an_old_code_or_slug_is_a_301_to_the_one_spelling_and_a_gone_one_drops_out(): void
    {
        ColourFacet::paint($this->product('Black one', $this->laptops), 'black');
        ColourFacet::paint($this->product('White one', $this->laptops), 'white');
        $aliases = $this->app->make(FilterAliases::class);

        $aliases->record('colour', 'en', FilterAliases::CODE, 'color', 'colour');
        $aliases->record('colour', 'en', FilterAliases::VALUE, 'noir', 'black');
        $aliases->record('colour', 'en', FilterAliases::VALUE, 'mauve', null);
        $aliases->record('p.gone', 'en', FilterAliases::CODE, 'gone', null);

        $this->get('/laptops/color_black')->assertStatus(301)->assertRedirect('/laptops/colour_black');
        $this->get('/laptops/colour_noir')->assertStatus(301)->assertRedirect('/laptops/colour_black');
        $this->get('/laptops/color_noir_white')->assertStatus(301)->assertRedirect('/laptops/colour_black_white');
        $this->get('/laptops/colour_black_mauve')->assertStatus(301)->assertRedirect('/laptops/colour_black');
        $this->get('/laptops/gone_anything/colour_black')->assertStatus(301)->assertRedirect('/laptops/colour_black');
        $this->get('/laptops/gone_anything')->assertStatus(301)->assertRedirect('/laptops');
        $this->get('/laptops/colour_nobody')->assertNotFound();
        $this->get('/laptops/nobody_black')->assertNotFound();
    }

    #[Test]
    public function a_chain_of_renames_leads_straight_to_the_last_one(): void
    {
        ColourFacet::paint($this->product('Black one', $this->laptops), 'black');
        $aliases = $this->app->make(FilterAliases::class);

        $aliases->record('colour', 'en', FilterAliases::CODE, 'kolor', 'color');
        $aliases->record('colour', 'en', FilterAliases::CODE, 'color', 'colour');

        $this->assertSame(['facet' => 'colour', 'target' => 'colour'], $aliases->code('kolor', 'en'));
        $this->get('/laptops/kolor_black')->assertStatus(301)->assertRedirect('/laptops/colour_black');

        // Renamed back: the spelling that is live again needs no alias.
        $aliases->record('colour', 'en', FilterAliases::CODE, 'colour', 'color');

        $this->assertNull($aliases->code('color', 'en'));
        $this->assertSame(['facet' => 'colour', 'target' => 'color'], $aliases->code('kolor', 'en'));
        $this->assertSame(2, DB::table('catalog_filter_aliases')->count());
    }

    #[Test]
    public function the_source_of_facets_is_asked_once_until_it_is_flushed(): void
    {
        $source = new PropertySource(['material', 'size']);
        $facets = $this->app->make(Facets::class);
        $facets->source($source);

        $this->assertSame(['category', 'price', 'colour', 'p.material', 'p.size'], $facets->keys());
        $this->assertNotNull($facets->find('p.size'));
        $this->assertSame($source, $facets->sourceOf('p.material'));
        $this->assertNull($facets->sourceOf('colour'));
        $this->get('/laptops')->assertOk();
        $this->assertSame(1, $source->asked);

        $facets->flush();
        $facets->all();

        $this->assertSame(2, $source->asked);
    }

    #[Test]
    public function by_share_the_first_ones_are_open_the_rest_are_more_and_nobody_s_are_out(): void
    {
        $ranked = FacetRelevance::ranked(['p.a' => 0.05, 'p.b' => 0.5, 'p.c' => 0.0, 'p.d' => 0.5, 'p.e' => 0.9, 'p.f' => 0.3], 0.1, 3);

        $this->assertSame(['p.e', 'p.b', 'p.d', 'p.f', 'p.a'], array_map(static fn (FacetRelevance $one): string => $one->key, $ranked));
        $this->assertSame([true, true, true, false, false], array_map(static fn (FacetRelevance $one): bool => $one->expanded, $ranked));
    }

    #[Test]
    public function a_page_counts_only_the_facets_its_source_keeps_and_a_chosen_one_always(): void
    {
        $source = new PropertySource(['material', 'size', 'rare', 'none'], minShare: 0.3, limit: 1);
        $this->app->make(Facets::class)->source($source);

        foreach (range(1, 10) as $i) {
            $product = $this->product("Laptop {$i}", $this->laptops);
            PropertySource::set($product, 'material', $i <= 8 ? 'steel' : 'wood');
            PropertySource::set($product, 'size', $i <= 5 ? 'small' : 'large');

            if ($i === 1) {
                PropertySource::set($product, 'rare', 'yes');
            }
        }

        $result = $this->search([]);

        $this->assertSame(['p.material', 'p.size', 'p.rare'], array_values(array_filter(array_keys($result->facets), static fn (string $key): bool => str_starts_with($key, 'p.'))));
        $this->assertTrue($result->facet('p.material')?->expanded);
        $this->assertFalse($result->facet('p.size')?->expanded);
        $this->assertFalse($result->facet('p.rare')?->expanded);
        $this->assertNull($result->facet('p.none'));
        $this->assertTrue($result->facet(PriceFacet::KEY)?->expanded);
        $this->assertSame(['steel' => 8, 'wood' => 2], $result->facet('p.material')->counts);

        // «rare» chosen: it narrows everything to one product, and stays open whatever its share.
        $chosen = $this->search(['p.rare' => FacetValue::of(['yes'])]);

        $this->assertTrue($chosen->facet('p.rare')?->expanded);
        $this->assertSame(['yes' => 1], $chosen->facet('p.rare')->counts);
        $this->assertNotContains('p.none', array_merge(...$source->batches));
        $this->assertNotContains('p.none', $source->singles);
    }

    #[Test]
    public function the_unchosen_facets_of_a_source_are_counted_in_one_query_and_a_chosen_one_alone(): void
    {
        $source = new PropertySource(['material', 'size', 'shape']);
        $this->app->make(Facets::class)->source($source);
        $one = $this->product('One', $this->laptops);
        $two = $this->product('Two', $this->laptops);
        PropertySource::set($one, 'material', 'steel');
        PropertySource::set($two, 'material', 'wood');
        PropertySource::set($one, 'size', 'small');
        PropertySource::set($two, 'shape', 'round');

        $result = $this->search([]);

        $this->assertSame([['p.material', 'p.size', 'p.shape']], $source->batches);
        $this->assertSame([], $source->singles);
        $this->assertSame(['steel' => 1, 'wood' => 1], $result->facet('p.material')?->counts);
        $this->assertSame(['round' => 1], $result->facet('p.shape')?->counts);

        $source->batches = [];
        $chosen = $this->search(['p.material' => FacetValue::of(['steel'])]);

        // Nobody found has a shape any more, so it is not counted at all.
        $this->assertSame([['p.size']], $source->batches);
        $this->assertSame(['p.material'], $source->singles);
        // Its own choice does not narrow it; the others' counts are narrowed by it.
        $this->assertSame(['steel' => 1, 'wood' => 1], $chosen->facet('p.material')?->counts);
        $this->assertSame(['small' => 1], $chosen->facet('p.size')?->counts);
        $this->assertNull($chosen->facet('p.shape'));
    }

    #[Test]
    public function a_search_contributor_finds_a_product_by_what_only_it_knows(): void
    {
        $source = new PropertySource(['material']);
        $this->app->make(SearchContributors::class)->register($source);
        PropertySource::set($this->product('Plain laptop', $this->laptops), 'material', 'titanium');
        $this->product('Titanium case', $this->laptops);
        $this->product('Other', $this->laptops);

        $found = $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(locale: 'en', search: 'titanium'));

        $this->assertSame(2, $found->total);
    }

    #[Test]
    public function the_facets_under_more_filters_are_a_details_of_links_on_the_search_page(): void
    {
        $this->app->make(Facets::class)->source(new PropertySource(['material', 'size'], minShare: 0.1, limit: 1));
        $one = $this->product('Laptop one', $this->laptops);
        $two = $this->product('Laptop two', $this->laptops);
        PropertySource::set($one, 'material', 'steel');
        PropertySource::set($two, 'material', 'wood');
        PropertySource::set($one, 'size', 'small');

        $page = (string) $this->get('/catalog/search?q=Laptop')->assertOk()->getContent();
        [$open, $more] = explode('<details class="webx-catalog-filter__more">', $page, 2) + [1 => ''];

        $this->assertStringContainsString('<legend>Material</legend>', $open);
        $this->assertStringContainsString('<summary>More filters</summary>', $more);
        $this->assertStringContainsString('<legend>Size</legend>', $more);
        $this->assertStringContainsString('/catalog/search/size_small?q=Laptop', $more);
    }

    #[Test]
    public function a_source_facet_the_category_settings_do_not_mention_comes_last_and_hidden_stays_hidden(): void
    {
        $this->app['config']->set('webx-catalog.fields.facets', true);
        $this->app->make(Facets::class)->source(new PropertySource(['material', 'size']));

        DB::table('catalog_category_facets')->insert([
            ['category_id' => $this->laptops->id, 'facet_key' => PriceFacet::KEY, 'is_visible' => true, 'position' => 0],
            ['category_id' => $this->laptops->id, 'facet_key' => 'p.size', 'is_visible' => false, 'position' => 1],
        ]);
        $this->app->make(CategoryFacets::class)->forget();

        $keys = array_map(static fn ($facet): string => $facet->key(), $this->app->make(CategoryFacets::class)->visible($this->laptops));

        $this->assertSame([PriceFacet::KEY, 'p.material'], $keys);
    }

    /**
     * The whole catalogue searched for nothing in particular, as a search page asks: every facet.
     *
     * @param  array<string, FacetValue>  $chosen
     */
    private function search(array $chosen): CatalogResult
    {
        $facets = $this->app->make(Facets::class);
        $context = new FilterContext(FilterContext::SEARCH, 'catalog/search', 'en', $facets->all());

        return $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(
            locale: 'en',
            context: FilterContext::SEARCH,
            facets: $chosen,
            count: array_values(array_filter($facets->keys(), static fn (string $key): bool => $key !== CategoryFacet::KEY)),
            filter: $context,
        ));
    }
}
