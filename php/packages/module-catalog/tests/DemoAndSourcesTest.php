<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Routing\Models\Route;

/**
 * The demo shop (§15) and what the rest of the panel reaches the catalogue by (§14): `products()`,
 * the link sources of a menu, the collection of a block, the module in `webx:setup`'s list.
 */
final class DemoAndSourcesTest extends TestCase
{
    #[Test]
    public function the_demo_fills_every_tab_and_is_removed_without_a_trace(): void
    {
        Storage::fake('public');

        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('catalog');
        $this->app->make(CatalogDemo::class)->seed($ledger);

        $this->assertSame(15, Category::query()->count());
        $this->assertSame(3, (int) Category::query()->max('depth') + 1, 'Three levels.');
        $this->assertSame(15, Route::query()->where('entity_type', (new Category)->getMorphClass())->count(), 'Every category has an address.');
        $this->assertGreaterThanOrEqual(150, Product::withTrashed()->count());
        $this->assertGreaterThan(0, Product::query()->where('is_published', false)->whereNotNull('category_id')->count());
        $this->assertGreaterThan(0, Product::onlyTrashed()->count());
        $this->assertSame(2, Product::query()->whereNull('category_id')->count());
        $this->assertSame(Product::withTrashed()->count(), ProductImage::query()->count());

        $path = ProductImage::query()->value('path');
        Storage::disk('public')->assertExists((string) $path);

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, Category::withTrashed()->count());
        $this->assertSame(0, Product::withTrashed()->count());
        $this->assertSame(0, ProductImage::query()->count());
        Storage::disk('public')->assertMissing((string) $path);
    }

    #[Test]
    public function the_demo_leaves_a_catalogue_that_has_anything_in_it_alone(): void
    {
        $this->product('Mine');

        $ledger = $this->app->make(DemoLedger::class);
        $this->app->make(CatalogDemo::class)->seed($ledger);

        $this->assertTrue($ledger->isEmpty());
        $this->assertNotEmpty($ledger->takeNotes());
    }

    #[Test]
    public function products_takes_a_category_with_its_subtree_and_a_sort(): void
    {
        $shoes = $this->category('shoes');
        $boots = $this->category('boots', parent: $shoes);
        $belts = $this->category('belts');

        $this->product('Cheap boot', $boots, ['price' => 10]);
        $this->product('Dear boot', $boots, ['price' => 90]);
        $this->product('Belt', $belts, ['price' => 50]);
        $this->product('Hidden boot', $boots, ['is_published' => false]);

        $cards = products()->category('shoes')->sort('price_desc')->get();

        $this->assertSame(['Dear boot', 'Cheap boot'], array_column($cards, 'name'));
        $this->assertSame(90.0, $cards[0]['price']);
        $this->assertContains($boots->id, $cards[0]['categories']);
        $this->assertSame(['Belt'], array_column(products()->category($belts)->get(), 'name'));
        $this->assertSame([], products()->category('nowhere')->get());
        $this->assertCount(1, products()->sort('popular')->take(1)->get());
    }

    #[Test]
    public function a_menu_links_to_categories_and_visible_products(): void
    {
        $shoes = $this->category('shoes');
        $hidden = $this->category('hidden', published: false);
        $boot = $this->product('Boot', $shoes, ['sku' => 'B-1']);
        $orphan = $this->product('Orphan');

        $links = $this->app->make(LinkSources::class);
        $products = $links->find(Product::TYPE);
        $categories = $links->find(Category::TYPE);

        $this->assertNotNull($products);
        $this->assertNotNull($categories);

        $found = $products->resolve([$boot->id, $orphan->id], 'en');
        $this->assertTrue($found[$boot->id]->available);
        $this->assertStringContainsString('B-1', (string) $found[$boot->id]->hint);
        $this->assertFalse($found[$orphan->id]->available);
        $this->assertSame([$boot->id], array_map(static fn ($one): int => $one->id, $products->search('B-1', 'en', 10)));

        $shelves = $categories->resolve([$shoes->id, $hidden->id], 'en');
        $this->assertTrue($shelves[$shoes->id]->available);
        $this->assertFalse($shelves[$hidden->id]->available);
    }

    #[Test]
    public function a_block_shows_products_and_setup_offers_the_catalogue(): void
    {
        $shoes = $this->category('shoes');
        $boot = $this->product('Boot', $shoes);

        $source = $this->app->make(CollectionSources::class)->find('products');
        $this->assertNotNull($source);
        $this->assertNull($source->categories());

        $items = $source->items(Selection::of([], $source), 'en');
        $this->assertSame([$boot->id], array_column($items, 'id'));
        $this->assertSame('product-'.$boot->id, $items[0]['anchor']);

        $catalogue = $this->app->make(Catalogue::class);
        $this->assertTrue($catalogue->knows('catalog'));
        $this->assertSame('webx-ui/module-catalog', $catalogue->packageFor('catalog'));
    }
}
