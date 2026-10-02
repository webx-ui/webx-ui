<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Demo\PropertiesDemo;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\CatalogProperties\Models\PropertyInterval;
use WebxUi\CatalogProperties\Models\PropertyValue;

/**
 * `webx:demo` of the properties (§12): every kind on the core demo's tree, and the journal holds only
 * the groups and the properties — the rest goes with them.
 */
final class DemoTest extends TestCase
{
    #[Test]
    public function the_properties_describe_the_core_demo_and_are_removed_without_a_trace(): void
    {
        Storage::fake('public');
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('catalog');
        $this->app->make(CatalogDemo::class)->seed($ledger);
        $ledger->forModule('catalog-properties');
        $this->app->make(PropertiesDemo::class)->seed($ledger);

        $this->assertSame(6, Property::query()->count());
        $this->assertSame(2, PropertyGroup::query()->count());
        $this->assertSame(11, PropertyValue::query()->count());
        $this->assertSame(3, PropertyInterval::query()->count());
        $this->assertSame(8, DB::table(PropertySets::TABLE)->count(), 'Clothing 1, Shoes 2, Kitchen 4, Cookware 1.');
        $this->assertGreaterThan(100, DB::table(ProductValues::TABLE)->count());

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, Property::withTrashed()->count());
        $this->assertSame(0, PropertyGroup::withTrashed()->count());
        $this->assertSame(0, PropertyValue::query()->count());
        $this->assertSame(0, DB::table(ProductValues::TABLE)->count());
        $this->assertSame(0, Product::withTrashed()->count());
    }
}
