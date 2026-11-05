<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Demo;

use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\Localization\Locales;

/**
 * Eight made-up brands on the demo shop's products (§7 of the dictionaries spec): four on the main
 * page, one taken off the site — its page answers 404 and its products show no brand.
 *
 * The products are the core demo's, counted by their order. Everything on the site has a brand that
 * is on the site too: a marketplace refuses a product without one, and the demo's feed is checked
 * against that rule (§14.2 of the feeds spec). What the shop keeps off the site shows the other two
 * cases in the panel: half has no brand, half the one taken off. Seven brands share no divisor with
 * the core's nine shelves or its every sixth sale, so each brand gets every shelf and a sale.
 * The links go with the brands: a brand forced out takes them ({@see Brand}).
 */
final class BrandsDemo
{
    /** slug => [name, featured, published] */
    private const BRANDS = [
        'northwind' => ['Northwind', true, true],
        'contoso' => ['Contoso', true, true],
        'fabrikam' => ['Fabrikam', false, true],
        'tailspin' => ['Tailspin', true, true],
        'litware' => ['Litware', false, true],
        'adatum' => ['Adatum', false, true],
        'proseware' => ['Proseware', false, false],
        'woodgrove' => ['Woodgrove', false, true],
    ];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $ids = $ledger->idsOf('catalog', Product::class);

        if ($ids === []) {
            $ledger->note('The catalogue demo is not seeded; the brands have no products to go on.');

            return;
        }

        if (Brand::withTrashed()->exists()) {
            $ledger->note('The site already has brands; the demo left them alone.');

            return;
        }

        $codes = $this->locales->codes();
        $default = $this->locales->defaultCode();
        $brands = [];
        $hidden = null;
        $position = 0;

        foreach (self::BRANDS as $slug => [$name, $featured, $published]) {
            $brand = new Brand;
            // A name is a name in every language; the slug is written in all of them, or the brand
            // has an address in one language only.
            $brand->setTranslations('title', array_fill_keys($codes, $name));
            $brand->setTranslations('slug', array_fill_keys($codes, $slug));
            $brand->setTranslations('description', [
                $default => '<p>'.$name.' is a made-up brand of the demo shop: no such company sells anything.</p>',
            ]);
            $brand->forceFill([
                'is_featured' => $featured,
                'is_visible' => $published,
                'position' => ++$position,
            ])->save();
            $ledger->created($brand, 'Brand '.$name);

            if ($published) {
                $brands[] = $brand->getKey();
            } else {
                $hidden = $brand->getKey();
            }
        }

        $rows = [];

        $products = Product::withTrashed()->whereKey($ids)->orderBy('id')->get(['id', 'is_published', 'deleted_at']);

        foreach ($products->values() as $n => $product) {
            if ($product->is_published && ! $product->trashed()) {
                $rows[] = ['product_id' => $product->id, 'brand_id' => $brands[$n % count($brands)]];
            } elseif ($n % 2 === 0) {
                $rows[] = ['product_id' => $product->id, 'brand_id' => $hidden];
            }
        }

        DB::table(Brand::LINKS)->insert($rows);
        $this->catalog->touchQuery(Product::withTrashed()->whereKey($ids));
    }
}
