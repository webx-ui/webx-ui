<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Popularity;

use Illuminate\Support\Facades\DB;

/**
 * The core's signal: views of the product page, already faded by the nightly recount (§9).
 */
final class ViewsSignal implements PopularitySignal
{
    public function key(): string
    {
        return 'views';
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, float>
     */
    public function values(array $productIds): array
    {
        return DB::table('catalog_product_popularity')
            ->whereIn('product_id', $productIds)
            ->pluck('views', 'product_id')
            ->mapWithKeys(static fn (mixed $views, mixed $id): array => [(int) $id => (float) $views])
            ->all();
    }
}
