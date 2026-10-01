<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Tests\TestCase;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Catalog\BrandFacet;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLabels\Catalog\LabelFacet;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\CatalogStock\Catalog\StockFacet;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * The satellites' facets asked of both engines alike (§6 of the Manticore spec): a brand, a label
 * or a stock status out of the filter is no value — not counted, not chosen — and a product
 * without a stock row counts under the default status. The database knows it from its joins;
 * an index knows it only if the document says so.
 */
abstract class SatelliteFacetScenarios extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            BrandsServiceProvider::class,
            LabelsServiceProvider::class,
            StockServiceProvider::class,
        ];
    }

    /** What the engine needs before it is asked: an engine with an index is handed the queue. */
    protected function settle(): void {}

    #[Test]
    public function a_hidden_brand_is_neither_counted_nor_chosen(): void
    {
        $laptops = $this->category('laptops');
        $apple = $this->brand('Apple', visible: true);
        $hidden = $this->brand('Ghost', visible: false);

        $a = $this->product('MacBook', $laptops);
        $b = $this->product('Ghost laptop', $laptops);
        $this->link(Brand::LINKS, 'brand_id', $a, $apple->id);
        $this->link(Brand::LINKS, 'brand_id', $b, $hidden->id);

        $all = $this->ask(count: [BrandFacet::KEY]);
        $this->assertSame(2, $all->total);
        $this->assertSame([(string) $apple->id => 1], $all->facet(BrandFacet::KEY)->counts ?? []);

        $this->assertSame([$a->id], $this->ask(facets: [BrandFacet::KEY => FacetValue::of([(string) $apple->id])])->ids);
    }

    #[Test]
    public function a_hidden_label_is_neither_counted_nor_chosen(): void
    {
        $laptops = $this->category('laptops');
        $sale = Label::query()->create(['title' => 'Sale', 'code' => 'sale', 'is_visible' => true]);
        $internal = Label::query()->create(['title' => 'Internal', 'code' => 'internal', 'is_visible' => false]);

        $a = $this->product('On sale', $laptops);
        $b = $this->product('Marked', $laptops);
        $this->link(Label::LINKS, 'label_id', $a, $sale->id);
        $this->link(Label::LINKS, 'label_id', $a, $internal->id);
        $this->link(Label::LINKS, 'label_id', $b, $internal->id);

        $all = $this->ask(count: [LabelFacet::KEY]);
        $this->assertSame([(string) $sale->id => 1], $all->facet(LabelFacet::KEY)->counts ?? []);
        $this->assertSame([$a->id], $this->ask(facets: [LabelFacet::KEY => FacetValue::of([(string) $sale->id])])->ids);
    }

    #[Test]
    public function a_product_without_a_row_counts_under_the_default_status_and_a_hidden_one_nowhere(): void
    {
        $laptops = $this->category('laptops');
        $inStock = StockStatus::query()->where('code', 'in-stock')->firstOrFail();
        $onOrder = StockStatus::query()->where('code', 'on-order')->firstOrFail();
        $service = StockStatus::query()->create(['title' => 'Service', 'code' => 'service', 'is_visible' => false]);

        $this->product('No row', $laptops);
        $ordered = $this->product('Ordered', $laptops);
        $kept = $this->product('Kept', $laptops);
        $this->link(StockStatus::LINKS, 'status_id', $ordered, $onOrder->id);
        $this->link(StockStatus::LINKS, 'status_id', $kept, $service->id);

        $all = $this->ask(count: [StockFacet::KEY]);
        $this->assertSame(3, $all->total);
        $counts = $all->facet(StockFacet::KEY)->counts ?? [];
        ksort($counts);
        $this->assertSame([(string) $inStock->id => 1, (string) $onOrder->id => 1], $counts);
    }

    private function brand(string $title, bool $visible): Brand
    {
        return Brand::query()->create(['title' => $title, 'slug' => strtolower($title), 'is_visible' => $visible]);
    }

    /** A link row, and the product marked — as the product form would after a save. */
    private function link(string $table, string $column, Product $product, int $id): void
    {
        DB::table($table)->insert(['product_id' => $product->id, $column => $id]);
        $product->touch();
    }

    /**
     * @param  array<string, FacetValue>  $facets
     * @param  list<string>  $count
     */
    private function ask(array $facets = [], array $count = []): CatalogResult
    {
        $this->settle();

        return $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(
            locale: 'en',
            context: 'category',
            facets: $facets,
            count: $count,
        ));
    }
}
