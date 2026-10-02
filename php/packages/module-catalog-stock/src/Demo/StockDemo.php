<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Demo;

use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogStock\Models\StockStatus;

/**
 * The demo shop's products in the migration's three statuses (§7 of the dictionaries spec): about
 * 80 % in stock, 10 % out, 10 % on order. Only some of those in stock get a row of their own — the
 * rest are in the default status, the way a live catalogue is.
 *
 * Nothing to create: the statuses are the migration's, and the journal has no entry for a row of a
 * link table. The rows go with the demo's products, whose deletion takes them.
 */
final class StockDemo
{
    public function __construct(private readonly Catalog $catalog) {}

    public function seed(DemoLedger $ledger): void
    {
        $ids = $ledger->idsOf('catalog', Product::class);

        if ($ids === []) {
            $ledger->note('The catalogue demo is not seeded; no products to put in stock.');

            return;
        }

        if (DB::table(StockStatus::LINKS)->whereIn('product_id', $ids)->exists()) {
            return;
        }

        $statuses = StockStatus::query()->pluck('id', 'code')->all();
        $rows = [];

        foreach (Product::withTrashed()->whereKey($ids)->orderBy('id')->pluck('id')->values() as $n => $id) {
            $code = match (true) {
                $n % 10 === 3 => 'out-of-stock',
                $n % 10 === 7 => 'on-order',
                $n % 4 === 0 => 'in-stock',
                default => null,
            };

            if ($code !== null && isset($statuses[$code])) {
                $rows[] = ['product_id' => $id, 'status_id' => $statuses[$code]];
            }
        }

        if ($rows === []) {
            $ledger->note('The site has none of the stock statuses the migration makes; the demo put nothing in stock.');

            return;
        }

        DB::table(StockStatus::LINKS)->insert($rows);
        $this->catalog->touchQuery(Product::withTrashed()->whereKey($ids));
    }
}
