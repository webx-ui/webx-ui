<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Rendering\ProductQuery;
use WebxUi\Catalog\Storefront\StorefrontCache;
use WebxUi\CatalogCollections\Catalog\CollectionPreset;
use WebxUi\CatalogCollections\Catalog\CollectionPresets;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Storefront\CollectionProducts;
use WebxUi\CatalogCollections\Storefront\LiveCollections;

/**
 * What a collection holds on the catalogue alone (§3, §5.1, §5.5): the order, the presets, the
 * rules of who may be seen, the limit and the two groups of its cache.
 */
final class CollectionProductsTest extends TestCase
{
    use CountsQueries;

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
     * @param  array<string, mixed>  $fields
     * @return list<int>
     */
    private function preset(string $key, array $fields = []): array
    {
        $presets = $this->app->make(CollectionPresets::class);
        $preset = $presets->find($key);
        $this->assertInstanceOf(CollectionPreset::class, $preset);

        return $this->ids($this->collection([...$presets->fields($preset), ...$fields]));
    }

    /**
     * @return list<int>
     */
    private function ids(ProductCollection $collection, ?int $limit = null, ?string $locale = null): array
    {
        return $this->app->make(CollectionProducts::class)->ids($collection, $limit, $locale);
    }

    private function presetOf(ProductCollection $collection): ?string
    {
        return $this->app->make(CollectionPresets::class)->of($collection)?->key;
    }

    private function age(Product $product, string $date): void
    {
        DB::table('catalog_products')->where('id', $product->id)->update(['created_at' => $date]);
    }

    #[Test]
    public function a_manual_collection_keeps_the_order_it_was_given(): void
    {
        [$first, $second, $third] = [$this->product('First'), $this->product('Second'), $this->product('Third')];

        $this->assertSame([$third->id, $first->id, $second->id], $this->ids($this->manual([$third, $first, $second])));
    }

    #[Test]
    public function a_product_that_cannot_be_seen_gives_its_place_to_the_next_on_the_list(): void
    {
        $hidden = $this->product('Hidden', ['is_published' => false]);
        $binned = $this->product('Binned');
        $kept = $this->product('Kept');
        $next = $this->product('Next');
        $last = $this->product('Last');

        $collection = $this->manual([$hidden, $binned, $kept, $next, $last], ['limit' => 2]);
        $binned->delete();

        $this->assertSame([$kept->id, $next->id], $this->ids($collection));
    }

    #[Test]
    public function a_product_with_no_address_in_the_language_is_left_out_of_it(): void
    {
        $both = $this->product('Both', ['slug' => ['en' => 'both', 'ru' => 'oba']]);
        $english = $this->product('English', ['slug' => ['en' => 'english']]);
        $collection = $this->manual([$english, $both]);

        $this->assertSame([$english->id, $both->id], $this->ids($collection, locale: 'en'));
        $this->assertSame([$both->id], $this->ids($collection, locale: 'ru'));
    }

    #[Test]
    public function an_empty_list_holds_nothing_and_asks_nothing_of_the_products(): void
    {
        $this->product('Unlisted');

        $this->assertSame([], $this->ids($this->collection()));
    }

    #[Test]
    public function a_manual_collection_does_not_read_the_fields_of_a_filter(): void
    {
        $cheap = $this->product('Cheap');
        $collection = $this->manual([$cheap], ['on_sale' => true, 'sort' => 'new', 'category_id' => 999999]);

        $this->assertSame([$cheap->id], $this->ids($collection));
    }

    #[Test]
    public function the_preset_new_gives_the_newest_first(): void
    {
        $old = $this->product('Old');
        $new = $this->product('New');
        $middle = $this->product('Middle');
        $this->age($old, '2026-01-01 00:00:00');
        $this->age($middle, '2026-02-01 00:00:00');
        $this->age($new, '2026-03-01 00:00:00');

        $this->assertSame([$new->id, $middle->id, $old->id], $this->preset(CollectionPresets::NEW));
    }

    #[Test]
    public function the_preset_on_sale_gives_what_has_an_old_price_above_the_price(): void
    {
        $sale = $this->product('Sale', ['price' => 10, 'old_price' => 15]);
        $this->product('Full price', ['price' => 10]);
        $this->product('Same price', ['price' => 10, 'old_price' => 10]);
        $this->product('Old price below', ['price' => 10, 'old_price' => 5]);
        $this->product('No price', ['price' => null, 'old_price' => 15]);

        $this->assertSame([$sale->id], $this->preset(CollectionPresets::ON_SALE));
    }

    #[Test]
    public function a_preset_of_a_facet_the_site_lacks_is_not_offered(): void
    {
        $presets = $this->app->make(CollectionPresets::class);

        $this->assertSame([CollectionPresets::NEW, CollectionPresets::ON_SALE], array_map(
            static fn (CollectionPreset $preset): string => $preset->key,
            $presets->all(),
        ));
        $this->assertNull($presets->find(CollectionPresets::LABEL));
    }

    #[Test]
    public function a_collection_answers_to_its_preset_while_the_fields_match_it(): void
    {
        $presets = $this->app->make(CollectionPresets::class);
        $new = $presets->find(CollectionPresets::NEW);
        $this->assertInstanceOf(CollectionPreset::class, $new);
        $collection = $this->collection($presets->fields($new));

        $this->assertSame(CollectionPresets::NEW, $this->presetOf($collection));

        $collection->category_id = 7;
        $this->assertSame(CollectionPresets::NEW, $this->presetOf($collection), 'A preset leaves the category alone.');

        $collection->sort = 'popular';
        $this->assertNull($this->presetOf($collection));

        $collection->sort = 'default';
        $this->assertNull($this->presetOf($collection), 'The default order with nothing else is no preset at all.');

        $collection->on_sale = true;
        $this->assertSame(CollectionPresets::ON_SALE, $this->presetOf($collection), 'The default order spelled out is the default order.');

        $collection->source = ProductCollection::MANUAL;
        $this->assertNull($this->presetOf($collection));
    }

    #[Test]
    public function a_category_takes_its_whole_branch(): void
    {
        $root = new Category(['name' => 'Cameras', 'slug' => 'cameras', 'is_published' => true]);
        $root->saveAsRoot();
        $child = new Category(['name' => 'Compact', 'slug' => 'compact', 'is_published' => true]);
        $child->appendTo($root);

        $inRoot = $this->product('In the root', ['category_id' => $root->id]);
        $inChild = $this->product('In the child', ['category_id' => $child->id]);
        $this->product('Elsewhere');

        $ids = $this->ids($this->filter(['category_id' => $root->id]));
        sort($ids);

        $this->assertSame([$inRoot->id, $inChild->id], $ids);
    }

    #[Test]
    public function a_category_that_is_gone_gives_nothing_rather_than_the_whole_catalogue(): void
    {
        $this->product('Anything');

        $this->assertSame([], $this->ids($this->filter(['category_id' => 999999])));
    }

    #[Test]
    public function a_sort_nobody_registered_is_the_default_order(): void
    {
        $product = $this->product('Only');

        $this->assertSame([$product->id], $this->ids($this->filter(['sort' => 'nobody-knows'])));
    }

    #[Test]
    public function a_filter_with_nothing_set_is_the_catalogue_up_to_the_limit(): void
    {
        foreach (range(1, 5) as $number) {
            $this->product('Product '.$number);
        }

        $this->assertCount(3, $this->ids($this->filter(['limit' => 3])));
    }

    #[Test]
    public function the_limit_of_a_place_only_lowers_the_limit_of_the_collection(): void
    {
        $products = array_map(fn (int $number): Product => $this->product('Product '.$number), range(1, 5));
        $collection = $this->manual($products, ['limit' => 3]);
        $three = array_map(static fn (Product $product): int => $product->id, array_slice($products, 0, 3));

        $this->assertSame(array_slice($three, 0, 2), $this->ids($collection, 2));
        $this->assertSame($three, $this->ids($collection, 3));
        $this->assertSame($three, $this->ids($collection, 5), 'A place cannot raise it.');
        $this->assertSame($three, $this->ids($collection, 0));
        $this->assertSame($three, $this->ids($collection, -1));
    }

    #[Test]
    public function a_lower_limit_cuts_the_same_entry_rather_than_making_another(): void
    {
        $collection = $this->manual([$this->product('First'), $this->product('Second')]);
        $this->ids($collection);

        $this->assertQueriesWithin(0, fn () => $this->ids($collection, 1));
    }

    #[Test]
    public function a_ceiling_lowered_in_the_config_reaches_a_collection_saved_under_the_old_one(): void
    {
        $collection = $this->manual([$this->product('First'), $this->product('Second'), $this->product('Third')]);
        $this->app['config']->set('webx-catalog-collections.max_limit', 2);

        $this->assertCount(2, $this->ids($collection));
    }

    #[Test]
    public function without_the_stock_satellite_the_setting_stays_and_nothing_is_dropped(): void
    {
        $this->assertFalse(ProductQuery::hasMacro('inStock'));

        $product = $this->product('Anything');
        $collection = $this->manual([$product]);

        $this->assertTrue($collection->only_purchasable);
        $this->assertSame([$product->id], $this->ids($collection));
    }

    #[Test]
    public function a_warm_collection_asks_the_database_nothing(): void
    {
        $collection = $this->manual([$this->product('First')]);
        $this->ids($collection);
        $this->app->make(CacheGroups::class)->forgetMemo();

        $this->assertQueriesWithin(0, fn () => $this->ids($collection));
    }

    #[Test]
    public function each_language_has_its_own_entry(): void
    {
        $product = $this->product('English', ['slug' => ['en' => 'english']]);
        $collection = $this->manual([$product]);

        $this->assertSame([$product->id], $this->ids($collection, locale: 'en'));
        $this->assertSame([], $this->ids($collection, locale: 'ru'));
    }

    #[Test]
    public function a_change_of_a_product_moves_the_catalogue_group_and_the_collection_follows(): void
    {
        $first = $this->product('First');
        $second = $this->product('Second');
        $collection = $this->manual([$first, $second]);
        $this->assertSame([$first->id, $second->id], $this->ids($collection));

        $first->update(['is_published' => false]);

        $this->assertSame([$second->id], $this->ids($collection));
    }

    #[Test]
    public function a_save_of_the_collection_moves_its_own_group_and_not_the_catalogue(): void
    {
        $first = $this->product('First');
        $second = $this->product('Second');
        $collection = $this->manual([$first, $second]);
        $this->assertSame([$first->id, $second->id], $this->ids($collection));

        $generation = $this->app->make(StorefrontCache::class)->generation();

        // The list is written beside the model, in the save that moves the group (§5.5).
        $collection->products()->sync([$second->id => ['position' => 0], $first->id => ['position' => 1]]);
        $collection->save();

        $this->assertSame([$second->id, $first->id], $this->ids($collection));
        $this->assertSame($generation, $this->app->make(StorefrontCache::class)->generation());
    }

    #[Test]
    public function with_the_cache_off_it_is_counted_every_time(): void
    {
        $first = $this->product('First');
        $collection = $this->manual([$first]);
        $this->app['config']->set('webx-catalog.cache.enabled', false);
        $this->assertSame([$first->id], $this->ids($collection));

        DB::table('catalog_products')->where('id', $first->id)->update(['is_published' => false]);

        $this->assertSame([], $this->ids($collection));
    }

    #[Test]
    public function a_source_nobody_answers_for_holds_nothing(): void
    {
        $collection = $this->manual([$this->product('First')]);
        $collection->source = 'mixed';

        $this->assertSame([], $this->ids($collection));
    }

    #[Test]
    public function a_live_collection_is_found_by_its_key_with_its_fields(): void
    {
        $this->filter(['key' => 'popular', 'limit' => 4, 'filters' => ['brand' => ['values' => ['3']]]]);

        $live = $this->app->make(LiveCollections::class)->find('popular');

        $this->assertInstanceOf(ProductCollection::class, $live);
        $this->assertSame(4, $live->limit);
        $this->assertSame('Picks', $live->label('en'));
        $this->assertSame(['brand' => ['values' => ['3']]], $live->filters);
    }

    #[Test]
    public function a_collection_found_live_reads_its_list(): void
    {
        $product = $this->product('First');
        $this->manual([$product], ['key' => 'picks']);

        $live = $this->app->make(LiveCollections::class)->find('picks');

        $this->assertInstanceOf(ProductCollection::class, $live);
        $this->assertSame([$product->id], $this->ids($live));
    }

    #[Test]
    public function off_in_the_bin_and_unknown_are_nothing_to_the_site(): void
    {
        $live = $this->app->make(LiveCollections::class);
        $this->collection(['key' => 'off', 'is_enabled' => false]);
        $this->collection(['key' => 'binned'])->delete();

        $this->assertNull($live->find('off'));
        $this->assertNull($live->find('binned'));
        $this->assertNull($live->find('unknown'));
        $this->assertNull($live->find(''));
    }

    #[Test]
    public function all_the_collections_of_the_site_are_one_read(): void
    {
        $this->collection(['key' => 'one']);
        $this->collection(['key' => 'two']);
        $live = $this->app->make(LiveCollections::class);
        $live->find('one');
        $this->app->make(CacheGroups::class)->forgetMemo();

        $this->assertQueriesWithin(0, static function () use ($live): void {
            $live->find('one');
            $live->find('two');
            $live->find('unknown');
        });
    }

    #[Test]
    public function a_collection_turned_off_binned_or_restored_is_seen_at_once(): void
    {
        $collection = $this->collection(['key' => 'picks']);
        $live = $this->app->make(LiveCollections::class);
        $this->assertNotNull($live->find('picks'));

        $collection->update(['is_enabled' => false]);
        $this->assertNull($live->find('picks'));

        $collection->update(['is_enabled' => true]);
        $this->assertNotNull($live->find('picks'));

        $collection->delete();
        $this->assertNull($live->find('picks'));

        $collection->restore();
        $this->assertNotNull($live->find('picks'));
    }
}
