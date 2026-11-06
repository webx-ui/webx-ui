<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Filter\FilterAliases;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogCollections\Catalog\CollectionPreset;
use WebxUi\CatalogCollections\Catalog\CollectionPresets;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Storefront\CollectionProducts;
use WebxUi\CatalogCollections\Storefront\LiveCollections;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * A collection beside the satellites it reads through the core: what can be bought (§5.2), a set of
 * labels and brands, and a value of that set merged or deleted (§3.5).
 */
final class CollectionSatellitesTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), StockServiceProvider::class, LabelsServiceProvider::class, BrandsServiceProvider::class];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function collection(array $attributes = []): ProductCollection
    {
        return ProductCollection::query()->create(['title' => 'Picks', ...$attributes])->refresh();
    }

    /**
     * @param  list<Product>  $products
     * @param  array<string, mixed>  $attributes
     */
    private function manual(array $products, array $attributes = []): ProductCollection
    {
        $collection = $this->collection($attributes);

        foreach ($products as $position => $product) {
            $collection->products()->attach($product->id, ['position' => $position]);
        }

        return $collection;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function filter(array $attributes = []): ProductCollection
    {
        return $this->collection(['source' => ProductCollection::FILTER, ...$attributes]);
    }

    /**
     * @return list<int>
     */
    private function ids(ProductCollection $collection): array
    {
        return $this->app->make(CollectionProducts::class)->ids($collection, locale: 'en');
    }

    /** One of the three statuses the migration makes: `in-stock`, `out-of-stock`, `on-order`. */
    private function stockStatus(string $code): StockStatus
    {
        return StockStatus::query()->where('code', $code)->firstOrFail();
    }

    private function inStatus(string $name, string $code): Product
    {
        $product = $this->product($name);
        DB::table(StockStatus::LINKS)->insert(['product_id' => $product->id, 'status_id' => $this->stockStatus($code)->id]);

        return $product;
    }

    private function brand(string $title): Brand
    {
        return Brand::query()->create(['title' => $title, 'slug' => strtolower($title), 'is_visible' => true])->refresh();
    }

    private function branded(string $name, Brand $brand): Product
    {
        $product = $this->product($name);
        DB::table(Brand::LINKS)->insert(['product_id' => $product->id, 'brand_id' => $brand->id]);

        return $product;
    }

    private function retarget(string $facet, int|string $from, int|string|null $to): void
    {
        $this->app->make(FilterAliases::class)->retarget($facet, FilterAliases::VALUE, (string) $from, $to === null ? null : (string) $to);
    }

    #[Test]
    public function on_order_stays_and_out_of_stock_goes(): void
    {
        $this->stockStatus('on-order')->update(['is_purchasable' => true]);
        $this->stockStatus('out-of-stock')->update(['is_purchasable' => false]);

        $onOrder = $this->inStatus('On order', 'on-order');
        $out = $this->inStatus('Out of stock', 'out-of-stock');
        $inStock = $this->inStatus('In stock', 'in-stock');
        $noRow = $this->product('No row');

        $this->assertSame([$onOrder->id, $inStock->id, $noRow->id], $this->ids($this->manual([$onOrder, $out, $inStock, $noRow])));
    }

    #[Test]
    public function the_rule_holds_for_a_filter_too_and_the_limit_counts_after_it(): void
    {
        $this->stockStatus('out-of-stock')->update(['is_purchasable' => false]);
        $out = $this->inStatus('Out of stock', 'out-of-stock');
        $kept = $this->inStatus('Kept', 'in-stock');
        DB::table('catalog_products')->where('id', $out->id)->update(['priority' => 10]);

        $this->assertSame([$kept->id], $this->ids($this->filter(['limit' => 1])));
    }

    #[Test]
    public function a_product_without_a_row_is_judged_by_the_default_status(): void
    {
        $product = $this->product('No row');
        $collection = $this->manual([$product]);
        $this->assertSame([$product->id], $this->ids($collection));

        $this->stockStatus('in-stock')->update(['is_purchasable' => false]);

        $this->assertSame([], $this->ids($collection));
    }

    #[Test]
    public function with_the_setting_off_every_status_stays(): void
    {
        $this->stockStatus('out-of-stock')->update(['is_purchasable' => false]);
        $out = $this->inStatus('Out of stock', 'out-of-stock');

        $this->assertSame([$out->id], $this->ids($this->manual([$out], ['only_purchasable' => false])));
    }

    #[Test]
    public function a_product_without_a_price_is_not_a_question_of_status(): void
    {
        $product = $this->product('Ask the price', ['price' => null]);

        $this->assertSame([$product->id], $this->ids($this->manual([$product])));
    }

    #[Test]
    public function a_change_of_the_flag_of_a_status_reaches_a_warm_collection(): void
    {
        $product = $this->inStatus('On order', 'on-order');
        $status = $this->stockStatus('on-order');
        $status->update(['is_purchasable' => true]);
        $collection = $this->manual([$product]);
        $this->assertSame([$product->id], $this->ids($collection));

        $status->update(['is_purchasable' => false]);
        $this->assertSame([], $this->ids($collection));

        $status->update(['is_purchasable' => true]);
        $this->assertSame([$product->id], $this->ids($collection));
    }

    #[Test]
    public function when_nothing_can_be_bought_the_collection_is_empty(): void
    {
        StockStatus::query()->get()->each(static fn (StockStatus $status) => $status->update(['is_purchasable' => false]));

        $this->assertSame([], $this->ids($this->manual([$this->product('Anything')])));
    }

    #[Test]
    public function the_preset_label_gives_what_carries_the_label(): void
    {
        $hit = Label::query()->create(['title' => 'Hit'])->refresh();
        $product = $this->product('Hit product');
        $hit->products()->attach($product->id);
        $this->product('Other');

        $presets = $this->app->make(CollectionPresets::class);
        $preset = $presets->find(CollectionPresets::LABEL);
        $this->assertInstanceOf(CollectionPreset::class, $preset);
        $collection = $this->collection($presets->fields($preset, [$hit->id]));

        $this->assertSame([$product->id], $this->ids($collection));
        $this->assertSame(CollectionPresets::LABEL, $presets->of($collection)?->key);
    }

    #[Test]
    public function a_brand_is_a_facet_of_the_set_like_any_other(): void
    {
        $brand = $this->brand('Acme');
        $product = $this->branded('Acme product', $brand);
        $this->branded('Other product', $this->brand('Other'));

        $this->assertSame([$product->id], $this->ids($this->filter(['filters' => ['brand' => ['values' => [(string) $brand->id]]]])));
    }

    #[Test]
    public function a_merged_value_moves_the_collection_to_the_new_one_without_a_word(): void
    {
        $from = $this->brand('From');
        $into = $this->brand('Into');
        $product = $this->branded('Product', $into);
        $collection = $this->filter(['filters' => ['brand' => ['values' => [(string) $from->id]]]]);
        $this->assertSame([], $this->ids($collection));

        $this->retarget('brand', $from->id, $into->id);
        $collection->refresh();

        $this->assertSame(['brand' => ['values' => [(string) $into->id]]], $collection->filters);
        $this->assertNull($collection->attention);
        $this->assertTrue($collection->is_enabled);
        $this->assertSame([$product->id], $this->ids($collection), 'The save moved the group.');
    }

    #[Test]
    public function a_value_merged_into_one_the_set_holds_is_one_value(): void
    {
        $collection = $this->filter(['filters' => ['brand' => ['values' => ['1', '2']]]]);

        $this->retarget('brand', 1, 2);

        $this->assertSame(['brand' => ['values' => ['2']]], $collection->refresh()->filters);
    }

    #[Test]
    public function a_deleted_value_leaves_the_set_and_marks_the_collection(): void
    {
        $collection = $this->filter(['filters' => ['brand' => ['values' => ['1', '2']]]]);

        $this->retarget('brand', 1, null);
        $collection->refresh();

        $this->assertSame(['brand' => ['values' => ['2']]], $collection->filters);
        $this->assertSame(ProductCollection::VALUE_DELETED, $collection->attention);
        $this->assertTrue($collection->is_enabled);
    }

    #[Test]
    public function a_set_emptied_by_a_deletion_turns_the_collection_off_rather_than_opening_the_catalogue(): void
    {
        $this->product('Anything');
        $collection = $this->filter(['key' => 'by-brand', 'filters' => ['brand' => ['values' => ['1']]]]);
        $live = $this->app->make(LiveCollections::class);
        $this->assertNotNull($live->find('by-brand'));

        $this->retarget('brand', 1, null);
        $collection->refresh();

        $this->assertNull($collection->filters);
        $this->assertSame(ProductCollection::VALUE_DELETED, $collection->attention);
        $this->assertFalse($collection->is_enabled);
        $this->assertNull($live->find('by-brand'));
    }

    #[Test]
    public function deleting_a_brand_for_real_reaches_the_collection(): void
    {
        $brand = $this->brand('Gone');
        $collection = $this->filter(['filters' => ['brand' => ['values' => [(string) $brand->id]]]]);

        $brand->delete();
        $collection->refresh();

        $this->assertFalse($collection->is_enabled);
        $this->assertSame(ProductCollection::VALUE_DELETED, $collection->attention);
    }

    #[Test]
    public function a_collection_with_an_empty_set_from_the_start_is_left_alone(): void
    {
        $collection = $this->filter(['sort' => 'new']);

        $this->retarget('brand', 1, null);
        $collection->refresh();

        $this->assertTrue($collection->is_enabled);
        $this->assertNull($collection->attention);
    }

    #[Test]
    public function a_collection_without_the_value_is_not_touched(): void
    {
        $collection = $this->filter(['filters' => ['brand' => ['values' => ['2']], 'label' => ['values' => ['1']]]]);
        $saved = $collection->updated_at;

        $this->retarget('brand', 1, null);
        $collection->refresh();

        $this->assertNull($collection->attention);
        $this->assertSame(['brand' => ['values' => ['2']], 'label' => ['values' => ['1']]], $collection->filters);
        $this->assertEquals($saved, $collection->updated_at);
    }

    #[Test]
    public function a_manual_collection_does_not_read_its_set_so_losing_a_value_does_not_turn_it_off(): void
    {
        $product = $this->product('Picked');
        $collection = $this->manual([$product], ['filters' => ['brand' => ['values' => ['1']]]]);

        $this->retarget('brand', 1, null);
        $collection->refresh();

        $this->assertNull($collection->filters);
        $this->assertNull($collection->attention);
        $this->assertTrue($collection->is_enabled);
        $this->assertSame([$product->id], $this->ids($collection));
    }

    #[Test]
    public function a_collection_in_the_bin_is_repaired_too(): void
    {
        $collection = $this->filter(['filters' => ['brand' => ['values' => ['1']]]]);
        $collection->delete();

        $this->retarget('brand', 1, 2);
        $collection->refresh();

        $this->assertTrue($collection->trashed());
        $this->assertSame(['brand' => ['values' => ['2']]], $collection->filters);
    }

    #[Test]
    public function a_link_to_a_module_that_has_gone_does_not_stop_the_repair(): void
    {
        $collection = $this->filter(['filters' => ['brand' => ['values' => ['1', '2']]]]);
        DB::table('catalog_collections')->where('id', $collection->id)->update([
            'link' => json_encode(['target' => 'entity', 'entity_type' => 'nobody.knows', 'entity_id' => 1]),
        ]);

        $this->retarget('brand', 1, null);

        $this->assertSame(['brand' => ['values' => ['2']]], $collection->refresh()->filters);
    }

    #[Test]
    public function a_new_link_is_still_checked_on_a_collection_that_exists(): void
    {
        $collection = $this->filter();

        $this->expectException(ValidationException::class);

        $collection->update(['link' => ['target' => 'url', 'url' => 'javascript:alert(1)']]);
    }
}
