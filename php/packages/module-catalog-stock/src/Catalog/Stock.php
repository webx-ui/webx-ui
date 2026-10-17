<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Support\RequestMemo;
use WebxUi\CatalogStock\Models\StockStatus;

/**
 * The status each product of a page is in, in two queries whatever the size of the page — the row
 * where there is one, the default where there is not — so the form, the list, the card, the rule
 * and the document all answer the same.
 */
final class Stock
{
    private const MEMO = 'stock.of.';

    /**
     * @param  list<int|string>  $productIds
     * @return array<int, StockStatus> product id → status; left out only where there is no default
     */
    public static function of(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        // Once a request for one set of products: the line on the card, the line beside the buy
        // button, the purchase rule and the document all ask for the same page.
        return RequestMemo::remember(self::MEMO.RequestMemo::ids($productIds), static fn (): array => self::lookup($productIds));
    }

    /** A status was put on a product, or taken off, in this request: what it knew is stale. */
    public static function forget(): void
    {
        RequestMemo::forget(self::MEMO);
        // The rule of the chain that reads the statuses answered from them.
        Container::getInstance()->make(Purchasability::class)->forget();
    }

    /**
     * @param  list<int|string>  $productIds
     * @return array<int, StockStatus>
     */
    private static function lookup(array $productIds): array
    {
        /** @var array<int, int> $rows */
        $rows = DB::table(StockStatus::LINKS)->whereIn('product_id', $productIds)->pluck('status_id', 'product_id')
            ->mapWithKeys(static fn (mixed $status, mixed $product): array => [(int) $product => (int) $status])
            ->all();

        // Trashed ones included: a status in the bin still holds the products left in it.
        $statuses = StockStatus::withTrashed()
            ->where(static fn (Builder $query): Builder => $query->whereKey(array_values(array_unique($rows)))->orWhere('is_default', true))
            ->get()
            ->keyBy('id');
        $fallback = $statuses->first(static fn (StockStatus $status): bool => $status->is_default && $status->deleted_at === null);

        $of = [];

        foreach ($productIds as $id) {
            $status = isset($rows[(int) $id]) ? $statuses->get($rows[(int) $id]) : $fallback;

            if ($status instanceof StockStatus) {
                $of[(int) $id] = $status;
            }
        }

        return $of;
    }

    /**
     * Narrow products to those in any of these statuses — the rows that say so, and, when the
     * default is among them, the products without a row.
     *
     * @param  Builder<covariant Product>  $query
     * @param  list<int>  $ids
     */
    public static function whereIn(Builder $query, array $ids): void
    {
        $key = $query->getModel()->qualifyColumn('id');
        $default = StockStatus::query()->where('is_default', true)->whereKey($ids)->exists();

        $query->where(static function (Builder $in) use ($key, $ids, $default): void {
            $in->whereIn($key, $in->getQuery()->newQuery()->from(StockStatus::LINKS)->select('product_id')->whereIn('status_id', $ids === [] ? [0] : $ids));

            if ($default) {
                $in->orWhereNotExists(static function (QueryBuilder $row) use ($key): void {
                    $row->from(StockStatus::LINKS)->whereColumn(StockStatus::LINKS.'.product_id', $key);
                });
            }
        });
    }
}
