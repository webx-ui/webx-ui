<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Panel\MethodForm;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;

/**
 * `webx:demo --module=delivery` (§18): a shop that delivers without a carrier — towns by hand, its
 * points of two kinds, its courier, the popular towns — and carrier A with it where the fake
 * carrier is on; all of it gone again with `--remove`.
 */
final class DemoTest extends PanelTestCase
{
    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-delivery.fake_carrier', $this->name() === 'carrier_a_comes_with_the_demo_and_binds_to_the_towns_made_by_hand');
    }

    #[Test]
    public function a_shop_without_a_carrier_is_seeded_by_hand_and_removed_whole(): void
    {
        $this->assertFalse($this->app->make(Carriers::class)->has(FakeCarrier::KEY), 'Off unless the site asks for it.');
        $ledger = $this->seedDemo();

        $towns = GeoLocality::query()->where('country_code', 'XX')->orderBy('id')->get();
        $this->assertSame(['Town A', 'Town B', 'Town C', 'Town D', 'Town E', 'Town F'], $towns->map(static fn (GeoLocality $town): string => $town->names['en'])->all());
        $this->assertSame(['Town A'], array_values(array_unique([$towns[0]->names['pl'], $towns[0]->names['en']])), 'A proper name in every language of the site.');
        $this->assertTrue($towns->every(static fn (GeoLocality $town): bool => $town->created_by === GeoLocality::MANUAL));
        $this->assertSame(2, GeoRegion::query()->where('country_code', 'XX')->count());
        $this->assertTrue(GeoCountry::query()->findOrFail('XX')->active);

        $points = DeliveryPoint::query()->where('carrier', Carriers::STORE)->get();
        $this->assertSame(['pickup' => 2, 'store' => 2], $points->countBy('kind')->sortKeys()->all());
        $this->assertTrue($points->every(static fn (DeliveryPoint $point): bool => $point->phone === '+800 1234 5678' && $point->active));

        $methods = DeliveryMethod::query()->with('localities')->orderBy('position')->get();
        $this->assertSame(['store.pickup', 'store.pickup', 'store.courier'], $methods->pluck('type')->all());
        $this->assertSame(['store', 'pickup', null], $methods->pluck('point_kind')->all());
        $this->assertSame('Odbiór w sklepie', $methods[0]->titles['pl']);
        $this->assertSame(1500, $methods[2]->price);
        $this->assertSame(['Town A', 'Town B'], $methods[2]->localities->map(static fn (GeoLocality $town): string => $town->names['en'])->all());
        $this->assertTrue($methods->every(static fn (DeliveryMethod $method): bool => $method->enabled));

        $popular = $this->app->make(PopularLocalities::class)->ids();
        $this->assertSame(['Town A', 'Town D', 'Town B'], array_map(static fn (int $id): string => GeoLocality::query()->findOrFail($id)->names['en'], $popular));

        // A second run on a shop that delivers leaves it alone.
        $entries = count($ledger->entries());
        $this->module()->seed($ledger);
        $this->assertCount($entries, $ledger->entries());
        $this->assertCount(1, $ledger->takeNotes());

        $this->undo($ledger);

        $this->assertSame(0, GeoLocality::query()->count());
        $this->assertSame(0, GeoRegion::query()->count());
        $this->assertNull(GeoCountry::query()->find('XX'));
        $this->assertSame(0, DeliveryPoint::query()->count());
        $this->assertSame(0, DeliveryMethod::query()->count());
        $this->assertSame(0, DB::table('delivery_popular_localities')->count());
    }

    #[Test]
    public function carrier_a_comes_with_the_demo_and_binds_to_the_towns_made_by_hand(): void
    {
        $this->assertTrue($this->app->make(Carriers::class)->has(FakeCarrier::KEY));
        $ledger = $this->seedDemo();

        $this->assertTrue(DeliveryCarrier::query()->findOrFail(FakeCarrier::KEY)->enabled);
        $this->assertSame([RunStatus::Succeeded], DeliverySyncRun::query()->pluck('status')->unique()->values()->all());
        $this->assertSame(36, DeliveryPoint::query()->where('carrier', FakeCarrier::KEY)->count());
        $this->assertGreaterThan(0, DB::table('delivery_streets')->count());

        // The carrier's «Region A» and «Region B» are the ones made by hand, not second ones.
        $this->assertSame(5, GeoRegion::query()->where('country_code', 'XX')->count());
        $this->assertSame(6, GeoLocality::query()->where('created_by', GeoLocality::MANUAL)->count());
        $this->assertSame(
            ['carrier-a.branch', 'carrier-a.courier', 'carrier-a.locker', 'store.courier', 'store.pickup'],
            DeliveryMethod::query()->distinct()->orderBy('type')->pluck('type')->all(),
        );

        // The list of the panel prices them as the checkout does: the carrier's by its rates, the shop's free.
        $form = $this->app->make(MethodForm::class);
        $rows = DeliveryMethod::query()->get()->mapWithKeys(static fn (DeliveryMethod $method): array => [$method->key => $form->row($method, 'en')['price_text']]);
        $this->assertSame('By carrier rates', $rows['carrier-a-branch']);
        $this->assertNull($rows['store-pickup'], 'Free — the list says it itself.');

        $this->undo($ledger);

        foreach (['geo_localities', 'geo_regions', 'geo_countries', 'delivery_points', 'delivery_streets', 'delivery_carrier_localities', 'delivery_sync_runs', 'delivery_methods'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} is left behind.");
        }

        $this->assertNull(DeliveryCarrier::query()->find(FakeCarrier::KEY));
    }

    private function module(): ProvidesDemo
    {
        $module = $this->app->make(ModuleRegistry::class)->get('delivery');
        $this->assertInstanceOf(ProvidesDemo::class, $module);
        $this->assertSame([], $module->requires());

        return $module;
    }

    private function seedDemo(): DemoLedger
    {
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('delivery');
        $this->module()->seed($ledger);
        $this->assertSame([], $ledger->takeNotes());

        return $ledger;
    }

    private function undo(DemoLedger $ledger): void
    {
        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }
    }
}
