<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Tests;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\CatalogBrands\Demo\BrandsDemo;
use WebxUi\CatalogLandings\Demo\LandingsDemo;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogLandings\Panel\LandingsModule;
use WebxUi\CatalogProperties\Demo\PropertiesDemo;
use WebxUi\Routing\Models\Route;

/**
 * `webx:demo` of the landings (§13): sets made of the satellites seeded before them, and a landing
 * whose facet the site lacks — labels are not installed here — left out.
 */
final class DemoTest extends TestCase
{
    #[Test]
    public function the_satellites_its_sets_are_made_of_are_asked_for_only_when_installed(): void
    {
        $module = $this->app->make(ModuleRegistry::class)->get('catalog-landings');

        $this->assertInstanceOf(LandingsModule::class, $module);
        $this->assertSame(['catalog', 'catalog-brands', 'catalog-properties'], $module->requires());
    }

    #[Test]
    public function the_landings_stand_on_the_demo_shop_and_are_removed_without_a_trace(): void
    {
        Storage::fake('public');
        $ledger = $this->app->make(DemoLedger::class);

        foreach (['catalog' => CatalogDemo::class, 'catalog-brands' => BrandsDemo::class, 'catalog-properties' => PropertiesDemo::class, 'catalog-landings' => LandingsDemo::class] as $id => $demo) {
            $ledger->forModule($id);
            $this->app->make($demo)->seed($ledger);
        }

        $this->assertSame(
            ['black-clothing', 'white-clothing', 'adatum-clothing', 'clothing-under-50', 'woodgrove-shoes', 'black-shoes', 'luxury-shoes'],
            Landing::query()->orderBy('position')->get()->map(static fn (Landing $landing): mixed => $landing->getTranslation('slug', 'en'))->all(),
        );
        $this->assertStringContainsString('New arrivals', implode(' ', $ledger->takeNotes()));
        $this->assertSame(3, Landing::query()->where('position', 1)->firstOrFail()->recommended()->count());

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, Landing::withTrashed()->count());
        $this->assertSame(0, Route::query()->where('entity_type', (new Landing)->getMorphClass())->count());
    }
}
