<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\Listing;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;

/**
 * §8.2 and §16: `SqlEngine` — a parent holds its whole subtree, a facet's counts ignore its own
 * choice, and the storefront keeps the engine's order.
 */
final class EngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ColourFacet::migrate();
        $this->app->make(Facets::class)->register(new ColourFacet);
    }

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
    public function the_category_facet_counts_every_ancestor_once_per_product(): void
    {
        $laptops = $this->category('laptops');
        $gaming = $this->category('gaming-laptops', parent: $laptops);

        $product = $this->product('Gaming laptop', $gaming);
        // An additional category in the same branch must not count the product twice.
        $product->categories()->attach($laptops->id);

        $result = $this->search(count: [CategoryFacet::KEY]);
        $counts = $result->facet(CategoryFacet::KEY)->counts ?? [];

        $this->assertSame(1, $counts[(string) $laptops->id] ?? null);
        $this->assertSame(1, $counts[(string) $gaming->id] ?? null);
    }

    #[Test]
    public function the_storefront_keeps_the_engines_order(): void
    {
        $laptops = $this->category('laptops');
        $cheap = $this->product('Cheap', $laptops, ['price' => 10]);
        $dear = $this->product('Dear', $laptops, ['price' => 1000]);
        $middle = $this->product('Middle', $laptops, ['price' => 100]);

        $result = $this->search(sort: 'price_desc');

        $this->assertSame([$dear->id, $middle->id, $cheap->id], $result->ids);
        $this->assertSame(
            [$dear->id, $middle->id, $cheap->id],
            $this->app->make(Listing::class)->products($result->ids)->map(static fn (Product $product): int => $product->id)->all(),
        );
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
        $this->assertSame([$deleted->id], $this->search(onlyTrashed: true)->ids);
    }

    #[Test]
    public function the_search_looks_in_the_name_the_article_number_and_the_barcode(): void
    {
        $laptops = $this->category('laptops');
        $byName = $this->product('ThinkPad X1', $laptops);
        $bySku = $this->product('Another', $laptops, ['sku' => 'TP-777']);
        $byBarcode = $this->product('Third', $laptops, ['barcode' => '4600000777001']);
        $this->product('Unrelated', $laptops);

        $this->assertSame([$byName->id], $this->search(search: 'thinkpad')->ids);
        $this->assertEqualsCanonicalizing([$bySku->id, $byBarcode->id], $this->search(search: '777')->ids);
    }

    #[Test]
    public function the_database_engine_keeps_no_index(): void
    {
        $this->assertFalse($this->app->make(Catalog::class)->needsIndex());
    }

    /**
     * @param  array<string, FacetValue>  $scope
     * @param  array<string, FacetValue>  $facets
     * @param  list<string>  $count
     */
    private function search(
        array $scope = [],
        array $facets = [],
        array $count = [],
        string $sort = 'default',
        ?string $search = null,
        bool $withUnpublished = false,
        bool $onlyTrashed = false,
        ?string $state = null,
    ): CatalogResult {
        return $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(
            locale: 'en',
            scope: $scope,
            facets: $facets,
            count: $count,
            search: $search,
            sort: $sort,
            withUnpublished: $withUnpublished,
            onlyTrashed: $onlyTrashed,
            state: $state,
        ));
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sorted(array $counts): array
    {
        ksort($counts);

        return $counts;
    }
}
