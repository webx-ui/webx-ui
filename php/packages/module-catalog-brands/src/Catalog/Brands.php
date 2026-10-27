<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Support\RequestMemo;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\Routing\RouteCache;

/**
 * The brand of each product of a page, in two queries whatever the size of the page, so the form,
 * the list, the card and the document all answer the same.
 */
final class Brands
{
    private const MEMO = 'brands.of.';

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

        // Once a request for one set of products: the line on the card, the line beside the buy
        // button and the document all ask for the same page.
        return RequestMemo::remember(self::MEMO.($visible ? 'visible.' : 'all.').RequestMemo::ids($productIds), static fn (): array => self::lookup($productIds, $visible));
    }

    /** A brand was put on a product, or taken off, in this request: what it knew is stale. */
    public static function forget(): void
    {
        RequestMemo::forget(self::MEMO);
    }

    /**
     * @param  list<int|string>  $productIds
     * @return array<int, Brand>
     */
    private static function lookup(array $productIds, bool $visible): array
    {
        /** @var array<int, int> $rows */
        $rows = DB::table(Brand::LINKS)->whereIn('product_id', $productIds)->pluck('brand_id', 'product_id')
            ->mapWithKeys(static fn (mixed $brand, mixed $product): array => [(int) $product => (int) $brand])
            ->all();

        if ($rows === []) {
            return [];
        }

        $query = $visible ? Brand::query()->visible() : Brand::withTrashed();
        $brands = $query->with('logo')->whereKey(array_values(array_unique($rows)))->get()->keyBy('id');

        // Noted rather than loaded: the product page reads its own addresses, its categories' and
        // these in one query, and none on a warm page.
        Container::getInstance()->make(RouteCache::class)->preload($brands->all());
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
