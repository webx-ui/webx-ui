<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\PriceFacet;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\Listing;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;
use WebxUi\Catalog\Tests\Fixtures\FixtureDocument;
use WebxUi\Catalog\Tests\Fixtures\PropertySource;

/**
 * The questions every engine answers alike (decision 26 of the Manticore spec, §8.1): a parent
 * holds its whole subtree, a facet's counts ignore its own choice, a tree counts each ancestor
 * once, a range has no missing prices in it, facets of one source count together, the panel sees
 * what the site does not, and the order is the engine's.
 *
 * `EngineTest` runs them on the database; an engine with an index runs the same class and hands
 * its index the queue before every question ({@see settle()}).
 */
abstract class EngineScenarios extends TestCase
{
    protected PropertySource $properties;

    protected function setUp(): void
    {
        parent::setUp();

        ColourFacet::migrate();
        PropertySource::migrate();

        $facets = $this->app->make(Facets::class);
        $facets->register(new ColourFacet);
        $facets->source($this->properties = new PropertySource(['colour', 'size']));
        $this->app->make(Documents::class)->register(new FixtureDocument);
    }

    /** What the engine needs before it is asked: an engine with an index is handed the queue. */
    protected function settle(): void {}

    #[Test]
    public function a_parent_category_holds_everything_below_it_as_main_or_additional(): void
    {
        $laptops = $this->category('laptops');
        $gaming = $this->category('gaming-laptops', parent: $laptops);
        $phones = $this->category('phones');

        $a = $this->product('Plain laptop', $laptops);
        $b = $this->product('Gaming laptop', $gaming);
        $c = $this->product('Phone with a keyboard', $phones);
        $c->categories()->attach($gaming->id);
        $c->touch();
        $this->product('Just a phone', $phones);

        $result = $this->search(scope: [CategoryFacet::KEY => FacetValue::of([(string) $laptops->id])]);

        $this->assertSame(3, $result->total);
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], $result->ids);
    }

    #[Test]
    public function a_facet_does_not_narrow_its_own_counts_but_narrows_the_others(): void
    {
        $laptops = $this->category('laptops');

        ColourFacet::paint($this->product('Black one', $laptops, ['price' => 100]), 'black');
        ColourFacet::paint($this->product('White one', $laptops, ['price' => 300]), 'white');
        ColourFacet::paint($this->product('Black and white', $laptops, ['price' => 500]), 'black', 'white');

        $result = $this->search(
            scope: [CategoryFacet::KEY => FacetValue::of([(string) $laptops->id])],
            facets: ['colour' => FacetValue::of(['black'])],
            count: ['colour', 'price'],
        );

        $this->assertSame(2, $result->total);
        // Choosing black still shows white beside it, with its own number.
        $this->assertSame(['black' => 2, 'white' => 2], $this->sorted($result->facet('colour')->counts ?? []));
        // The price's ends are of the black ones only.
        $price = $result->facet('price');
        $this->assertNotNull($price);
        $this->assertSame(100.0, $price->min);
        $this->assertSame(500.0, $price->max);
    }

    #[Test]
    public function a_product_without_a_price_is_outside_every_range_and_its_ends(): void
    {
        $laptops = $this->category('laptops');
        $cheap = $this->product('Cheap', $laptops, ['price' => 99.99]);
        $this->product('On request', $laptops);
        $dear = $this->product('Dear', $laptops, ['price' => 1500]);

        $all = $this->search(count: [PriceFacet::KEY]);
        $this->assertSame(3, $all->total);
        $ends = $all->facet(PriceFacet::KEY);
        $this->assertNotNull($ends);
        $this->assertSame(99.99, $ends->min);
        $this->assertSame(1500.0, $ends->max);

        $from = $this->search(facets: [PriceFacet::KEY => FacetValue::range(0, null)], count: [PriceFacet::KEY]);
        $this->assertEqualsCanonicalizing([$cheap->id, $dear->id], $from->ids);
        // A range's own choice does not narrow its own ends.
        $this->assertSame(1500.0, $from->facet(PriceFacet::KEY)?->max);

        $this->assertSame([$cheap->id], $this->search(facets: [PriceFacet::KEY => FacetValue::range(null, 99.99)])->ids);
    }

    #[Test]
    public function the_category_facet_counts_every_ancestor_once_per_product(): void
    {
        $laptops = $this->category('laptops');
        $gaming = $this->category('gaming-laptops', parent: $laptops);

        $product = $this->product('Gaming laptop', $gaming);
        // An additional category in the same branch must not count the product twice.
        $product->categories()->attach($laptops->id);
        $product->touch();

        $result = $this->search(count: [CategoryFacet::KEY]);
        $counts = $result->facet(CategoryFacet::KEY)->counts ?? [];

        $this->assertSame(1, $counts[(string) $laptops->id] ?? null);
        $this->assertSame(1, $counts[(string) $gaming->id] ?? null);
    }

    #[Test]
    public function facets_of_one_source_count_together_and_a_chosen_one_by_itself(): void
    {
        $shirts = $this->category('shirts');
        $a = $this->product('Red small', $shirts);
        PropertySource::set($a, 'colour', 'red');
        PropertySource::set($a, 'size', 's');
        $b = $this->product('Red large', $shirts);
        PropertySource::set($b, 'colour', 'red');
        PropertySource::set($b, 'size', 'l');
        $c = $this->product('Blue large', $shirts);
        PropertySource::set($c, 'colour', 'blue');
        PropertySource::set($c, 'size', 'l');

        $result = $this->search(facets: ['p.size' => FacetValue::of(['l'])], count: ['p.colour', 'p.size']);

        $this->assertEqualsCanonicalizing([$b->id, $c->id], $result->ids);
        $this->assertSame(['blue' => 1, 'red' => 1], $this->sorted($result->facet('p.colour')->counts ?? []));
        $this->assertSame(['l' => 2, 's' => 1], $this->sorted($result->facet('p.size')->counts ?? []));
    }

    #[Test]
    public function the_storefront_keeps_the_engines_order(): void
    {
        $laptops = $this->category('laptops');
        $cheap = $this->product('Cheap', $laptops, ['price' => 10]);
        $dear = $this->product('Dear', $laptops, ['price' => 1000]);
        $middle = $this->product('Middle', $laptops, ['price' => 100]);
        $free = $this->product('On request', $laptops);

        $result = $this->search(sort: 'price_desc');

        // A product without a price is last both ways.
        $this->assertSame([$dear->id, $middle->id, $cheap->id, $free->id], $result->ids);
        $this->assertSame([$cheap->id, $middle->id, $dear->id, $free->id], $this->search(sort: 'price_asc')->ids);
        $this->assertSame(
            [$dear->id, $middle->id, $cheap->id, $free->id],
            $this->app->make(Listing::class)->products($result->ids)->map(static fn (Product $product): int => $product->id)->all(),
        );
        $this->assertSame([$cheap->id, $dear->id, $middle->id, $free->id], $this->search(sort: 'name')->ids);
    }

    #[Test]
    public function the_default_order_is_priority_then_score_then_the_newest(): void
    {
        $laptops = $this->category('laptops');
        // Created in the reverse of the order they should come in, so the id cannot decide.
        $pinned = $this->product('Pinned', $laptops, ['priority' => 5]);
        $popular = $this->product('Popular', $laptops);
        $new = $this->product('New', $laptops);
        $old = $this->product('Old', $laptops);

        foreach ([$pinned->id => 9, $popular->id => 8, $new->id => 1, $old->id => 3] as $id => $days) {
            DB::table('catalog_products')->where('id', $id)->update(['created_at' => now()->subDays($days)]);
        }

        DB::table('catalog_product_popularity')->insert(['product_id' => $popular->id, 'views' => 10, 'score' => 10]);

        $this->assertSame([$pinned->id, $popular->id, $new->id, $old->id], $this->search()->ids);
        // "Popular" is the score alone: the hand-set priority does not lift anything there.
        $this->assertSame($popular->id, $this->search(sort: 'popular')->ids[0]);
    }

    #[Test]
    public function the_site_sees_visible_products_and_the_panel_sees_every_one(): void
    {
        $laptops = $this->category('laptops');
        $hidden = $this->category('archive', published: false);

        $visible = $this->product('On sale', $laptops);
        $invisible = $this->product('In a hidden category', $hidden);
        $unpublished = $this->product('Unpublished', $laptops, ['is_published' => false]);
        $orphan = $this->product('Nowhere');
        $deleted = $this->product('Deleted', $laptops);
        $deleted->delete();

        $this->assertSame([$visible->id], $this->search()->ids);

        $panel = $this->search(withUnpublished: true);
        $this->assertEqualsCanonicalizing([$visible->id, $invisible->id, $unpublished->id, $orphan->id], $panel->ids);

        $this->assertSame([$orphan->id], $this->search(withUnpublished: true, state: CatalogQuery::STATE_NO_CATEGORY)->ids);
        $this->assertEqualsCanonicalizing([$unpublished->id, $orphan->id], $this->search(withUnpublished: true, state: CatalogQuery::STATE_UNPUBLISHED)->ids);
        $this->assertSame([$deleted->id], $this->search(onlyTrashed: true)->ids);
    }

    #[Test]
    public function the_search_finds_a_word_of_the_name(): void
    {
        $laptops = $this->category('laptops');
        $byName = $this->product('ThinkPad X1', $laptops);
        $this->product('Unrelated', $laptops);

        $this->assertSame([$byName->id], $this->search(search: 'thinkpad')->ids);
    }

    #[Test]
    public function the_product_whose_code_the_search_is_comes_first_and_is_named(): void
    {
        $plugs = $this->category('plugs');
        $mention = $this->product('Adapter for AT-1234/56 and others', $plugs, ['priority' => 9]);
        $plug = $this->product('Spark plug', $plugs, ['sku' => 'AT-1234/56']);
        $scanned = $this->product('Ignition coil', $plugs, ['barcode' => '4601234567890']);
        $imported = $this->product('Glow plug', $plugs, ['external_id' => 'ext-77']);

        // Above a higher priority: the code is what was asked for (decision 20 of the Manticore spec).
        $result = $this->search(search: 'AT-1234/56');
        $this->assertSame([$plug->id, $mention->id], $result->ids);
        $this->assertSame([$plug->id], $result->exact);

        $this->assertSame([$scanned->id], $this->search(search: '4601234567890')->exact);
        $this->assertSame([$imported->id], $this->search(search: 'ext-77')->ids);
        $this->assertSame([$imported->id], $this->search(search: 'ext-77')->exact);

        // A word is not a code: found, and nobody's code.
        $this->assertSame([], $this->search(search: 'spark')->exact);
        // The panel is told too, about a product the site does not show.
        $hidden = $this->product('Unpublished plug', $plugs, ['sku' => 'NGK-1', 'is_published' => false]);
        $this->assertSame([], $this->search(search: 'NGK-1')->exact);
        $this->assertSame([$hidden->id], $this->search(search: 'NGK-1', withUnpublished: true)->exact);
    }

    #[Test]
    public function what_the_query_language_reads_as_an_operator_is_a_character_typed(): void
    {
        $plugs = $this->category('plugs');
        $this->product('Spark plug', $plugs, ['sku' => 'AT-1234/56']);

        foreach (['AT-1234/56"', '"/-\'', 'AT-1234 | 56', '(spark)', 'spark*', '@name spark'] as $typed) {
            // An answer, never an error of the query language.
            $this->assertLessThanOrEqual(1, $this->search(search: $typed)->total, $typed);
        }

        $this->assertSame([], $this->search(search: '"/-\'')->ids);
    }

    #[Test]
    public function pages_follow_one_another_without_a_gap(): void
    {
        $laptops = $this->category('laptops');
        $ids = [];

        foreach (range(1, 5) as $index) {
            $ids[] = $this->product('Laptop '.$index, $laptops)->id;
        }

        $first = $this->search(perPage: 2);
        $second = $this->search(perPage: 2, page: 2);
        $third = $this->search(perPage: 2, page: 3);

        $this->assertSame(5, $second->total);
        $this->assertEqualsCanonicalizing($ids, [...$first->ids, ...$second->ids, ...$third->ids]);
        $this->assertCount(1, $third->ids);
    }

    /**
     * @param  array<string, FacetValue>  $scope
     * @param  array<string, FacetValue>  $facets
     * @param  list<string>  $count
     */
    protected function search(
        array $scope = [],
        array $facets = [],
        array $count = [],
        string $sort = 'default',
        ?string $search = null,
        bool $withUnpublished = false,
        bool $onlyTrashed = false,
        ?string $state = null,
        int $page = 1,
        int $perPage = 24,
    ): CatalogResult {
        $this->settle();

        return $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(
            locale: 'en',
            context: $withUnpublished || $onlyTrashed ? 'panel' : 'category',
            scope: $scope,
            facets: $facets,
            count: $count,
            search: $search,
            sort: $sort,
            page: $page,
            perPage: $perPage,
            withUnpublished: $withUnpublished,
            onlyTrashed: $onlyTrashed,
            state: $state,
        ));
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    protected function sorted(array $counts): array
    {
        ksort($counts);

        return $counts;
    }
}
