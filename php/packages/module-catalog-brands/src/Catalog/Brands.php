<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use Illuminate\Support\Facades\DB;
use WebxUi\CatalogBrands\Models\Brand;

/**
 * The brand of each product of a page, in two queries whatever the size of the page, so the form,
 * the list, the card and the document all answer the same.
 */
final class Brands
{
    /**
     * @param  list<int|string>  $productIds
     * @param  bool  $visible  only the published ones — what the site shows; the panel sees all
     * @return array<int, Brand> product id → brand; a product without one is left out
     */
    public static function of(array $productIds, bool $visible = false): array
    {
        if ($productIds === []) {
            return [];
        }

        /** @var array<int, int> $rows */
        $rows = DB::table(Brand::LINKS)->whereIn('product_id', $productIds)->pluck('brand_id', 'product_id')
            ->mapWithKeys(static fn (mixed $brand, mixed $product): array => [(int) $product => (int) $brand])
            ->all();

        if ($rows === []) {
            return [];
        }

        $query = $visible ? Brand::query()->visible() : Brand::withTrashed();
        $brands = $query->with(['logo', 'routes'])->whereKey(array_values(array_unique($rows)))->get()->keyBy('id');
        $of = [];

        foreach ($rows as $product => $id) {
            $brand = $brands->get($id);

            if ($brand instanceof Brand) {
                $of[$product] = $brand;
            }
        }

        return $of;
    }
}
