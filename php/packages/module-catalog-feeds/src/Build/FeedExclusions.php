<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * The products a person took out of one feed (§5.2 of the feeds spec). They stay in the others, and
 * the file changes with the next build.
 */
final class FeedExclusions
{
    public const AT_ONCE = 200;

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function page(Feed $feed, string $term, int $perPage, ?int $page = null): LengthAwarePaginator
    {
        $query = Product::query()->whereIn('catalog_products.id', static function (QueryBuilder $rows) use ($feed): void {
            $rows->select('product_id')->from(FeedSelection::EXCLUSIONS)->where('feed_id', $feed->id);
        });

        if ($term !== '') {
            $query->matching($term);
        }

        return $query->orderBy('catalog_products.id')->paginate($perPage, page: $page);
    }

    /**
     * @param  array<array-key, Product>  $products
     * @return list<array{id: int, name: string, sku: string|null}>
     */
    public function rows(Feed $feed, array $products): array
    {
        return array_values(array_map(static fn (Product $product): array => [
            'id' => (int) $product->id,
            'name' => $product->displayName($feed->locale),
            'sku' => $product->sku,
        ], $products));
    }

    /**
     * Which of these products the feed holds out now.
     *
     * @param  list<int>  $products
     * @return list<int>
     */
    public function among(Feed $feed, array $products): array
    {
        return DB::table(FeedSelection::EXCLUSIONS)
            ->where('feed_id', $feed->id)
            ->whereIn('product_id', $products)
            ->pluck('product_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $products
     */
    public function add(Feed $feed, array $products): void
    {
        $now = Carbon::now();

        DB::table(FeedSelection::EXCLUSIONS)->insertOrIgnore(array_map(static fn (int $id): array => [
            'feed_id' => $feed->id,
            'product_id' => $id,
            'created_at' => $now,
        ], $products));
    }

    /**
     * @param  list<int>  $products
     */
    public function remove(Feed $feed, array $products): void
    {
        DB::table(FeedSelection::EXCLUSIONS)->where('feed_id', $feed->id)->whereIn('product_id', $products)->delete();
    }
}
