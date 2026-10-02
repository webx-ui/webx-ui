<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\CatalogStock\Demo\StockDemo;
use WebxUi\CatalogStock\Models\StockStatus;

/**
 * `webx:demo` of the stock: the core demo's products in the migration's statuses, gone with them.
 */
final class DemoTest extends TestCase
{
    #[Test]
    public function the_core_demo_is_put_in_stock_and_the_rows_go_with_its_products(): void
    {
        Storage::fake('public');
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('catalog');
        $this->app->make(CatalogDemo::class)->seed($ledger);
        $ledger->forModule('catalog-stock');
        $this->app->make(StockDemo::class)->seed($ledger);

        $statuses = StockStatus::query()->pluck('code', 'id');
        $counts = DB::table(StockStatus::LINKS)->get()->countBy(static fn (object $row): string => (string) $statuses[$row->status_id]);
        $this->assertSame(15, $counts['out-of-stock']);
        $this->assertSame(15, $counts['on-order']);
        $this->assertGreaterThan(0, $counts['in-stock']);
        $this->assertSame(3, StockStatus::query()->count(), 'Nothing of its own: the statuses are the migration\'s.');

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, DB::table(StockStatus::LINKS)->count());
        $this->assertSame(3, StockStatus::query()->count());
    }
}
