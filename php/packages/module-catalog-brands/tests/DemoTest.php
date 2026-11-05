<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Tests;

use Illuminate\Foundation\Application;
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
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The demo's pictures go in as they are: what the pipeline makes of an upload is the media
        // module's test, and turning every demo photo into webp was a third of this one.
        $app['config']->set('webx-media.optimize.enabled', false);
    }

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

        $onSite = Product::query()->where('is_published', true)->pluck('id');
        $visible = Brand::query()->where('is_visible', true)->pluck('id');
        $this->assertGreaterThan(0, $onSite->count());
        $this->assertSame(
            $onSite->count(),
            DB::table(Brand::LINKS)->whereIn('product_id', $onSite)->whereIn('brand_id', $visible)->count(),
            'Everything on the site has a brand on the site: a feed cannot go without one.',
        );

        $links = DB::table(Brand::LINKS)->pluck('product_id');
        $hidden = Brand::query()->where('is_visible', false)->value('id');
        $this->assertGreaterThan(0, Product::withTrashed()->whereKeyNot($links->all())->count(), 'The filter «no brand» has something to find.');
        $this->assertGreaterThan(0, DB::table(Brand::LINKS)->where('brand_id', $hidden)->count(), 'The brand taken off has products in the panel.');

        // The brands go first: they were seeded after the products.
        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, Brand::withTrashed()->count());
        $this->assertSame(0, DB::table(Brand::LINKS)->count());
        $this->assertSame(0, Product::withTrashed()->count());
    }
}
