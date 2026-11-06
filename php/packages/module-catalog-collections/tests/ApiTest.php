<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Storefront\CollectionProducts;
use WebxUi\CatalogLabels\LabelsServiceProvider;

/**
 * §6 of the collections spec: the API the section of the panel reads and writes, and its refusals.
 */
final class ApiTest extends TestCase
{
    use CountsQueries;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), LabelsServiceProvider::class, BrandsServiceProvider::class];
    }

    private function brand(string $title): Brand
    {
        return Brand::query()->create(['title' => $title, 'slug' => strtolower($title), 'is_visible' => true])->refresh();
    }

    #[Test]
    public function without_blocks_nothing_is_counted_which_is_not_the_same_as_none(): void
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks']);
        $user = $this->manager();

        $this->actingAs($user, 'cms')->getJson($this->api())->assertOk()->assertJsonPath('data.0.places', null);
        $this->actingAs($user, 'cms')->getJson($this->api($collection->id.'/usage'))->assertOk()->assertExactJson(['data' => []]);
    }

    #[Test]
    public function a_collection_is_created_with_its_list_in_order_and_read_back(): void
    {
        $first = $this->product('Camera');
        $second = $this->product('Lens');

        $id = $this->actingAs($this->manager(), 'cms')->postJson($this->api(), [
            'title' => ['en' => 'For beginners', 'ru' => 'Для новичка'],
            'source' => 'manual',
            'limit' => 4,
            'view' => 'carousel',
            'link_text' => ['en' => 'All cameras'],
            'products' => [$second->id, $first->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'for-beginners')
            ->assertJsonPath('data.title.ru', 'Для новичка')
            ->assertJsonPath('data.limit', 4)
            ->assertJsonPath('data.view', 'carousel')
            ->assertJsonPath('data.link_text.en', 'All cameras')
            ->assertJsonPath('data.products', [$second->id, $first->id])
            ->assertJsonPath('data.product_items.0.name', 'Lens')
            ->json('data.id');

        $this->actingAs($this->manager(['catalog.view']), 'cms')->getJson($this->api((string) $id))->assertOk()->assertJsonPath('data.products_count', 2);
    }

    #[Test]
    public function the_list_is_searched_by_title_and_key_and_filtered(): void
    {
        ProductCollection::query()->create(['title' => 'New arrivals', 'key' => 'fresh']);
        ProductCollection::query()->create(['title' => 'Picks', 'is_enabled' => false]);
        ProductCollection::query()->create(['title' => 'Marked', 'attention' => ProductCollection::VALUE_DELETED]);
        ProductCollection::query()->create(['title' => 'Binned'])->delete();

        $keys = fn (string $query): array => array_column($this->actingAs($this->manager(), 'cms')->getJson($this->api().$query)->assertOk()->json('data'), 'key');

        $this->assertSame(['fresh', 'picks', 'marked'], $keys(''));
        $this->assertSame(['fresh'], $keys('?q=arriv'));
        $this->assertSame(['fresh'], $keys('?q=fre'));
        $this->assertSame(['picks'], $keys('?enabled=0'));
        $this->assertSame(['marked'], $keys('?attention=1'));
        $this->assertSame(['binned'], $keys('?trashed=1'));
    }

    #[Test]
    public function the_bin_opens_on_what_was_thrown_away_last(): void
    {
        foreach (['first', 'second'] as $key) {
            ProductCollection::query()->create(['title' => $key, 'key' => $key]);
        }

        ProductCollection::query()->where('key', 'second')->first()?->delete();
        $this->travel(1)->minutes();
        ProductCollection::query()->where('key', 'first')->first()?->delete();

        $rows = $this->actingAs($this->manager(), 'cms')->getJson($this->api().'?trashed=1')->assertOk()->json('data');

        $this->assertSame(['first', 'second'], array_column($rows, 'key'));
    }

    #[Test]
    public function the_list_costs_the_same_for_two_rows_and_for_twenty(): void
    {
        $product = $this->product('Camera');
        $make = function (int $count) use ($product): void {
            for ($at = 0; $at < $count; $at++) {
                ProductCollection::query()->create(['title' => 'Row '.uniqid()])->products()->attach($product->id, ['position' => 1]);
            }
        };
        $editor = $this->manager();
        $ask = fn () => $this->actingAs($editor, 'cms')->getJson($this->api())->assertOk();

        $make(2);
        $ask();
        $few = $this->recordQueries($ask)->count();
        $make(18);

        $this->assertSame($few, $this->recordQueries($ask)->count());
        $this->assertNoRepeatedQueries($ask, 'the list of collections');
    }

    #[Test]
    public function a_change_of_the_list_alone_reaches_a_warm_collection(): void
    {
        $first = $this->product('Camera');
        $second = $this->product('Lens');
        $collection = ProductCollection::query()->create(['title' => 'Picks']);
        $collection->products()->attach($first->id, ['position' => 1]);
        $products = $this->app->make(CollectionProducts::class);

        $this->assertSame([$first->id], $products->ids($collection, locale: 'en'));

        $this->actingAs($this->manager(), 'cms')->putJson($this->api((string) $collection->id), ['products' => [$second->id]])->assertOk();

        $this->assertSame([$second->id], $products->ids($collection->refresh(), locale: 'en'));
    }

    #[Test]
    public function a_save_clears_the_mark_of_attention_and_keeps_what_it_was_not_sent(): void
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'limit' => 6, 'attention' => ProductCollection::VALUE_DELETED]);

        $this->actingAs($this->manager(), 'cms')->putJson($this->api((string) $collection->id), ['view' => 'carousel'])
            ->assertOk()
            ->assertJsonPath('data.attention', null)
            ->assertJsonPath('data.limit', 6);
    }

    #[Test]
    public function the_key_is_refused_under_its_field_once_it_is_set(): void
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks']);

        $this->actingAs($this->manager(), 'cms')->putJson($this->api((string) $collection->id), ['key' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        // The form sends what it read; that is no change.
        $this->actingAs($this->manager(), 'cms')->putJson($this->api((string) $collection->id), ['key' => 'picks'])->assertOk();
    }

    #[Test]
    public function a_limit_that_is_no_whole_number_and_words_that_are_no_text_are_refused(): void
    {
        $manager = $this->manager();

        foreach ([[1], 1.5, true, 'many'] as $limit) {
            $this->actingAs($manager, 'cms')->postJson($this->api(), ['title' => 'Picks', 'limit' => $limit])
                ->assertUnprocessable()
                ->assertJsonPath('errors.limit.0', 'The limit is a number from 1 to 48.');
        }

        $this->actingAs($manager, 'cms')->postJson($this->api('preview'), ['source' => 'manual', 'limit' => [1]])->assertUnprocessable();
        $this->actingAs($manager, 'cms')->postJson($this->api(), ['title' => 'Picks', 'link_text' => ['en' => ['x']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('link_text.en');

        $this->assertSame(0, ProductCollection::query()->count());
    }

    #[Test]
    public function a_blank_switch_is_refused_under_its_field(): void
    {
        $manager = $this->manager();

        foreach (['on_sale', 'only_purchasable', 'is_enabled'] as $field) {
            foreach (['', '   '] as $blank) {
                $this->actingAs($manager, 'cms')->postJson($this->api(), ['title' => 'Picks', 'source' => 'filter', $field => $blank])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors($field);
            }
        }

        $this->assertSame(0, ProductCollection::query()->count());
    }

    #[Test]
    public function the_limit_names_the_ceiling_in_its_refusal(): void
    {
        $this->actingAs($this->manager(), 'cms')->postJson($this->api(), ['title' => 'Picks', 'limit' => 49])
            ->assertUnprocessable()
            ->assertJsonPath('errors.limit.0', 'The limit is a number from 1 to 48.');
    }

    #[Test]
    public function a_hand_picked_list_stops_at_the_ceiling_and_names_a_product_nobody_has(): void
    {
        config(['webx-catalog-collections.max_limit' => 2]);
        $ids = [$this->product('One')->id, $this->product('Two')->id, $this->product('Three')->id];

        $this->actingAs($this->manager(), 'cms')->postJson($this->api(), ['title' => 'Picks', 'limit' => 2, 'products' => $ids])
            ->assertUnprocessable()
            ->assertJsonPath('errors.products.0', 'A list holds at most 2 products.');

        $this->actingAs($this->manager(), 'cms')->postJson($this->api(), ['title' => 'Picks', 'limit' => 2, 'products' => [999999]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.products.0', 'There is no product #999999.');

        $this->assertSame(0, ProductCollection::query()->count());
    }

    #[Test]
    public function a_filter_is_held_to_its_conditions_and_a_hand_picked_list_is_not(): void
    {
        $base = ['title' => 'Picks', 'source' => 'filter'];
        $editor = $this->manager();

        $this->actingAs($editor, 'cms')->postJson($this->api(), [...$base, 'filters' => ['nothing' => ['values' => [1]]]])
            ->assertUnprocessable()->assertJsonValidationErrors('filters');
        $this->actingAs($editor, 'cms')->postJson($this->api(), [...$base, 'filters' => ['category' => ['values' => [1]]]])
            ->assertUnprocessable()->assertJsonValidationErrors('filters');
        $this->actingAs($editor, 'cms')->postJson($this->api(), [...$base, 'sort' => 'nothing'])
            ->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->actingAs($editor, 'cms')->postJson($this->api(), [...$base, 'category_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');

        $this->actingAs($editor, 'cms')->postJson($this->api(), ['title' => 'Picks', 'source' => 'manual', 'filters' => ['nothing' => ['values' => [1]]]])->assertCreated();
    }

    #[Test]
    public function a_collection_goes_to_the_bin_and_comes_back_with_its_key(): void
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks']);
        $editor = $this->manager();

        $this->actingAs($editor, 'cms')->deleteJson($this->api((string) $collection->id))->assertNoContent();
        $this->assertSoftDeleted($collection);

        // Read in the bin, written only out of it.
        $this->actingAs($editor, 'cms')->getJson($this->api((string) $collection->id))->assertOk();
        $this->actingAs($editor, 'cms')->putJson($this->api((string) $collection->id), ['limit' => 3])->assertNotFound();

        $this->actingAs($editor, 'cms')->postJson($this->api("{$collection->id}/restore"))
            ->assertOk()
            ->assertJsonPath('data.key', 'picks')
            ->assertJsonPath('data.deleted_at', null);
    }

    #[Test]
    public function a_collection_is_deleted_for_good_only_from_the_bin_and_frees_its_key(): void
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks']);
        $collection->products()->attach($this->product('Camera')->id, ['position' => 1]);
        $editor = $this->manager();

        // A live one is not in reach: the bin is the first step, always.
        $this->actingAs($editor, 'cms')->deleteJson($this->api("{$collection->id}/purge"))->assertNotFound();
        $this->assertNotSoftDeleted($collection);

        $collection->delete();

        // Reading the catalogue is not enough for what cannot be undone.
        $this->actingAs($this->manager(['catalog.view']), 'cms')->deleteJson($this->api("{$collection->id}/purge"))->assertForbidden();

        $this->actingAs($editor, 'cms')->deleteJson($this->api("{$collection->id}/purge"))->assertNoContent();

        $this->assertSame(0, ProductCollection::withTrashed()->count());
        $this->assertDatabaseCount(ProductCollection::PRODUCTS, 0);
        $this->actingAs($editor, 'cms')->getJson($this->api('?trashed=1'))->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($editor, 'cms')->getJson($this->api())->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($editor, 'cms')->postJson($this->api(), ['title' => 'Again', 'key' => 'picks', 'source' => 'manual'])
            ->assertCreated()
            ->assertJsonPath('data.key', 'picks');
    }

    #[Test]
    public function the_facets_of_a_base_come_with_the_presets_of_the_site(): void
    {
        $category = new Category(['name' => 'Cameras', 'slug' => 'cameras', 'is_published' => true]);
        $category->saveAsRoot();
        $brand = $this->brand('Nikon');
        $product = $this->product('Camera', ['category_id' => $category->id]);
        DB::table(Brand::LINKS)->insert(['product_id' => $product->id, 'brand_id' => $brand->id]);

        $answer = $this->actingAs($this->manager(), 'cms')->getJson($this->api('facets').'?category='.$category->id)->assertOk();

        $this->assertContains('brand', array_column($answer->json('data'), 'key'));
        $this->assertSame(['new', 'on-sale', 'label'], array_column($answer->json('presets'), 'key'));
        $this->actingAs($this->manager(), 'cms')->getJson($this->api('facets').'?category=999999')->assertNotFound();
    }

    #[Test]
    public function the_form_reads_what_a_filter_was_saved_with(): void
    {
        $brand = $this->brand('Nikon');
        $collection = ProductCollection::query()->create([
            'title' => 'Sale',
            'source' => ProductCollection::FILTER,
            'on_sale' => true,
            'filters' => ['brand' => ['values' => [$brand->id]]],
        ]);

        $this->actingAs($this->manager(), 'cms')->getJson($this->api((string) $collection->id))
            ->assertOk()
            ->assertJsonPath('data.filters.brand.values', [(string) $brand->id])
            ->assertJsonPath('data.filter_labels.brand.'.$brand->id, 'Nikon')
            ->assertJsonPath('data.preset', null);
    }

    #[Test]
    public function the_form_says_the_ceiling_and_has_no_switch_without_the_stock_satellite(): void
    {
        config(['webx-catalog-collections.max_limit' => 12]);

        $nodes = [];
        $walk = static function (array $list) use (&$walk, &$nodes): void {
            foreach ($list as $node) {
                $nodes[$node['id']] = $node;
                $walk($node['children'] ?? []);
            }
        };
        $walk($this->app->make(ScreenRegistry::class)->tree(ProductCollection::SCREEN));

        $this->assertSame(12, $nodes['limit']['props']['max']);
        $this->assertSame(12, $nodes['products']['props']['max']);
        $this->assertArrayNotHasKey('only-purchasable', $nodes);
    }
}
