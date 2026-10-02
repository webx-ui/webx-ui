<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogLabels\Demo\LabelsDemo;
use WebxUi\CatalogLabels\Models\Label;

/**
 * `webx:demo` of the labels: on the core demo's products, and gone with them.
 */
final class DemoTest extends TestCase
{
    #[Test]
    public function the_labels_go_on_the_core_demo_and_are_removed_without_a_trace(): void
    {
        Storage::fake('public');
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('catalog');
        $this->app->make(CatalogDemo::class)->seed($ledger);
        $ledger->forModule('catalog-labels');
        $this->app->make(LabelsDemo::class)->seed($ledger);

        $this->assertSame(['top', 'sale', 'new', 'newsletter'], Label::query()->orderBy('position')->pluck('code')->all());
        $sale = (int) Label::query()->where('code', 'sale')->value('id');
        $this->assertSame(
            Product::withTrashed()->whereNotNull('old_price')->count(),
            DB::table(Label::LINKS)->where('label_id', $sale)->count(),
            'Sale is where the old price is.',
        );

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, Label::withTrashed()->count());
        $this->assertSame(0, DB::table(Label::LINKS)->count());
        $this->assertSame(0, Product::withTrashed()->count());
    }

    #[Test]
    public function without_the_core_demo_there_is_nothing_to_label(): void
    {
        $ledger = $this->app->make(DemoLedger::class);
        $this->app->make(LabelsDemo::class)->seed($ledger);

        $this->assertTrue($ledger->isEmpty());
        $this->assertSame(0, Label::withTrashed()->count());
        $this->assertNotEmpty($ledger->takeNotes());
    }
}
