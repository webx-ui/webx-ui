<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogBrands\Demo\BrandsDemo;
use WebxUi\CatalogBrands\Models\Brand;

/**
 * `webx:demo` of the brands: on the core demo's products, and taken out before them — which the
 * link without a cascade would refuse, were a forced delete not to take its links.
 */
final class DemoTest extends TestCase
{
    #[Test]
    public function the_brands_go_on_the_core_demo_and_are_removed_without_a_trace(): void
    {
        Storage::fake('public');
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('catalog');
        $this->app->make(CatalogDemo::class)->seed($ledger);
        $ledger->forModule('catalog-brands');
        $this->app->make(BrandsDemo::class)->seed($ledger);

        $this->assertSame(8, Brand::withTrashed()->count());
        $this->assertSame(1, Brand::query()->where('is_visible', false)->count(), 'One taken off the site.');
        $products = Product::withTrashed()->count();
        $this->assertSame($products - intdiv($products + 3, 7), DB::table(Brand::LINKS)->count(), 'Every seventh without a brand.');

        // The brands go first: they were seeded after the products.
        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, Brand::withTrashed()->count());
        $this->assertSame(0, DB::table(Brand::LINKS)->count());
        $this->assertSame(0, Product::withTrashed()->count());
    }
}
