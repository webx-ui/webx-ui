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
 * The products are the core demo's, counted by their order. Every seventh has no brand — not every
 * sixth, which is where the core puts its old prices, or a brand's page would never show a sale.
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
            $brands[] = $brand->getKey();
        }

        $rows = [];

        foreach (Product::withTrashed()->whereKey($ids)->orderBy('id')->pluck('id')->values() as $n => $id) {
            if ($n % 7 !== 3) {
                // Shifted every eight, or a brand would only ever get the odd products or the even ones.
                $rows[] = ['product_id' => $id, 'brand_id' => $brands[($n + intdiv($n, count($brands))) % count($brands)]];
            }
        }

        DB::table(Brand::LINKS)->insert($rows);
        $this->catalog->touchQuery(Product::withTrashed()->whereKey($ids));
    }
}
