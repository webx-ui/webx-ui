<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\MethodOption;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\QuoteKind;
use WebxUi\Delivery\Tests\Fakes\FakeBranchType;
use WebxUi\Delivery\Tests\Fakes\FakeCarrierA;
use WebxUi\Delivery\Tests\Fakes\FakeCourierType;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Geography\Models\GeoLocality;

/**
 * What a buyer is offered in a locality (§6.3) — on localities made by hand, with no carrier but
 * the shop itself unless a test registers «carrier A».
 */
final class MethodsTest extends TestCase
{
    /** @return list<string> */
    private function offered(GeoLocality $town, ?CartState $cart = null): array
    {
        return array_map(
            static fn (MethodOption $option): string => $option->method->key,
            $this->app->make(Methods::class)->forLocality($this->context($town, $cart)),
        );
    }

    private function option(GeoLocality $town, string $key): MethodOption
    {
        foreach ($this->app->make(Methods::class)->evaluate($this->context($town)) as $option) {
            if ($option->method->key === $key) {
                return $option;
            }
        }

        $this->fail("No method {$key} was evaluated.");
    }

    private function withCarrierA(): void
    {
        $this->app->make(Carriers::class)->register(FakeCarrierA::class);
        $types = $this->app->make(MethodTypes::class);
        $types->register(FakeBranchType::class);
        $types->register(FakeCourierType::class);
        DeliveryCarrier::query()->create(['key' => 'carrier-a', 'enabled' => true]);
    }

    /** @param  list<string>  $features */
    private function bind(GeoLocality $town, array $features = ['courier'], int $streets = 10): DeliveryCarrierLocality
    {
        return DeliveryCarrierLocality::query()->create([
            'carrier' => 'carrier-a',
            'external_id' => 'a-'.$town->id,
            'locality_id' => $town->id,
            'status' => 'matched',
            'method' => 'auto_exact',
            'record' => ['name' => $town->name('en')],
            'features' => $features,
            'streets' => $streets,
        ]);
    }

    #[Test]
    public function a_pick_up_without_a_point_in_the_locality_is_not_offered(): void
    {
        $townA = $this->town('Town A');
        $townB = $this->town('Town B');
        $this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store']);
        $this->point($townA);

        $this->assertSame(['store-pickup'], $this->offered($townA));
        $this->assertSame([], $this->offered($townB));
        $this->assertSame(MethodOption::NO_POINTS, $this->option($townB, 'store-pickup')->unavailable);
        $this->assertSame(1, $this->option($townA, 'store-pickup')->points);
    }

    #[Test]
    public function only_an_active_point_of_the_methods_own_kind_counts(): void
    {
        $town = $this->town('Town A');
        $this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store']);
        $this->method('pickup-points', StorePickup::KEY, ['point_kind' => 'pickup', 'position' => 1]);
        $this->point($town, 'pickup', ['active' => false]);
        $this->point($town, 'store', ['removed_at' => now()]);

        $this->assertSame([], $this->offered($town));

        $this->point($town, 'pickup');

        // Two pick-ups of the shop are two methods of one type, each with its kind (decision 9).
        $this->assertSame(['pickup-points'], $this->offered($town));
    }

    #[Test]
    public function the_shops_courier_goes_only_to_its_own_localities_at_the_methods_price(): void
    {
        $townA = $this->town('Town A');
        $townB = $this->town('Town B');
        $townC = $this->town('Town C');
        $courier = $this->method('our-courier', StoreCourier::KEY, ['price' => 1500]);

        // None chosen — nowhere: the shop turns its courier on explicitly (§10.2).
        $this->assertSame([], $this->offered($townA));

        $courier->syncLocalities([$townA->id, $townB->id]);

        foreach ([$townA, $townB] as $town) {
            $option = $this->option($town, 'our-courier');
            $this->assertTrue($option->offered());
            $this->assertSame(QuoteKind::Fixed, $option->quote?->kind);
            $this->assertSame(1500, $option->quote->amount);
            $this->assertTrue($option->quote->inTotal);
            $this->assertNull($option->points);
        }

        $this->assertSame([], $this->offered($townC));
        $this->assertSame(MethodOption::NOT_AVAILABLE, $this->option($townC, 'our-courier')->unavailable);
    }

    #[Test]
    public function a_method_without_a_price_or_at_zero_is_free(): void
    {
        $town = $this->town('Town A');
        $this->point($town);
        $this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store']);
        $this->method('pickup-zero', StorePickup::KEY, ['point_kind' => 'store', 'price' => 0]);

        foreach (['store-pickup', 'pickup-zero'] as $key) {
            $quote = $this->option($town, $key)->quote;
            $this->assertSame(QuoteKind::Free, $quote?->kind);
            $this->assertNull($quote->was);
            $this->assertFalse($quote->inTotal);
        }
    }

    #[Test]
    public function the_order_is_the_panels_and_switched_off_methods_are_left_out(): void
    {
        $town = $this->town('Town A');
        $this->point($town);
        $this->method('second', StorePickup::KEY, ['point_kind' => 'store', 'position' => 2]);
        $this->method('first', StorePickup::KEY, ['point_kind' => 'store', 'position' => 1]);
        $this->method('off', StorePickup::KEY, ['point_kind' => 'store', 'position' => 0, 'enabled' => false]);

        $this->assertSame(['first', 'second'], $this->offered($town));
    }

    #[Test]
    public function a_method_of_another_country_or_of_a_switched_off_carrier_is_not_offered(): void
    {
        $town = $this->town('Town A');
        $this->point($town);
        $this->method('elsewhere', StorePickup::KEY, ['point_kind' => 'store', 'countries' => ['XY']]);
        $this->method('here', StorePickup::KEY, ['point_kind' => 'store', 'countries' => ['XX']]);

        $this->assertSame(['here'], $this->offered($town));
        $this->assertSame(MethodOption::COUNTRY, $this->option($town, 'elsewhere')->unavailable);

        DeliveryCarrier::query()->whereKey(Carriers::STORE)->first()?->update(['enabled' => false]);

        $this->assertSame([], $this->offered($town));
        $this->assertSame(MethodOption::CARRIER_OFF, $this->option($town, 'here')->unavailable);
    }

    #[Test]
    public function a_method_whose_type_is_gone_is_left_out_with_the_reason(): void
    {
        $town = $this->town('Town A');
        $this->method('gone', 'carrier-z.branch');

        $this->assertSame([], $this->offered($town));
        $this->assertSame(MethodOption::TYPE_MISSING, $this->option($town, 'gone')->unavailable);
    }

    #[Test]
    public function a_carriers_point_method_needs_the_carriers_point_of_its_kind(): void
    {
        $this->withCarrierA();
        $town = $this->town('Town A');
        $this->method('carrier-a-branch', 'carrier-a.branch', ['point_kind' => 'branch']);
        $this->point($town, 'branch', ['carrier' => 'carrier-a', 'external_id' => 'b-1']);
        // The shop's store of the same locality is not the carrier's branch.
        $this->point($town, 'store');

        $option = $this->option($town, 'carrier-a-branch');
        $this->assertTrue($option->offered());
        $this->assertSame(QuoteKind::CarrierRates, $option->quote?->kind);
        $this->assertSame(7000, $option->quote->estimate);
        $this->assertFalse($option->quote->inTotal);
    }

    #[Test]
    public function a_carriers_courier_needs_its_binding_and_without_streets_is_shown_disabled(): void
    {
        $this->withCarrierA();
        $bound = $this->town('Town A');
        $unbound = $this->town('Town B');
        $noStreets = $this->town('Town C');
        $noCourier = $this->town('Town D');
        $this->method('carrier-a-courier', 'carrier-a.courier');
        $this->bind($bound);
        $this->bind($noStreets, streets: 0);
        $this->bind($noCourier, features: []);

        $option = $this->option($bound, 'carrier-a-courier');
        $this->assertTrue($option->offered());
        $this->assertFalse($option->disabled);
        $this->assertSame('a-'.$bound->id, FakeCourierType::$lastCarrierLocality);

        $this->assertSame(MethodOption::NOT_BOUND, $this->option($unbound, 'carrier-a-courier')->unavailable);
        $this->assertSame(MethodOption::NOT_BOUND, $this->option($noCourier, 'carrier-a-courier')->unavailable);

        $disabled = $this->option($noStreets, 'carrier-a-courier');
        $this->assertTrue($disabled->offered());
        $this->assertTrue($disabled->disabled);
        $this->assertSame('Courier delivery is not available here, choose another method', $disabled->hint);
        $this->assertSame(MethodOption::NO_STREETS, $disabled->reason());
    }

    #[Test]
    public function an_unavailable_quote_leaves_the_method_out_but_keeps_the_reason(): void
    {
        $this->withCarrierA();
        $town = $this->town('Town A');
        $this->method('carrier-a-courier', 'carrier-a.courier');
        $this->bind($town);

        $this->assertSame([], $this->offered($town, $this->cart([[true, true, 200000]])));

        $option = null;

        foreach ($this->app->make(Methods::class)->evaluate($this->context($town, $this->cart([[true, true, 200000]]))) as $evaluated) {
            $option = $evaluated;
        }

        $this->assertSame(MethodOption::NO_QUOTE, $option?->unavailable);
        $this->assertSame('Too heavy for a courier', $option->reason());
    }

    #[Test]
    public function a_warm_list_costs_the_points_and_the_bindings_of_the_locality_and_nothing_per_method(): void
    {
        $this->withCarrierA();
        $town = $this->town('Town A');
        $this->point($town);
        $this->point($town, 'branch', ['carrier' => 'carrier-a', 'external_id' => 'b-1']);
        $this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store']);
        $this->method('carrier-a-branch', 'carrier-a.branch', ['point_kind' => 'branch']);
        $this->method('carrier-a-courier', 'carrier-a.courier');
        $this->method('our-courier', StoreCourier::KEY, ['price' => 900])->syncLocalities([$town->id]);
        $this->bind($town);

        $context = $this->context($town);
        $methods = $this->app->make(Methods::class);
        $methods->forLocality($context);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $offered = $methods->forLocality($context);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(4, $offered);
        $this->assertCount(2, $queries, 'the points by carrier and kind, the bindings: '.json_encode(array_column($queries, 'query')));
    }

    #[Test]
    public function the_shops_methods_alone_never_read_the_bindings(): void
    {
        $town = $this->town('Town A');
        $this->method('our-courier', StoreCourier::KEY, ['price' => 900])->syncLocalities([$town->id]);

        $context = $this->context($town);
        $methods = $this->app->make(Methods::class);
        $methods->forLocality($context);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $methods->forLocality($context);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries, 'a courier asks for no point and has no carrier binding');
    }

    #[Test]
    public function the_cached_list_follows_the_panel(): void
    {
        $townA = $this->town('Town A');
        $townB = $this->town('Town B');
        $courier = $this->method('our-courier', StoreCourier::KEY, ['price' => 900]);
        $courier->syncLocalities([$townA->id]);

        $this->assertSame(['our-courier'], $this->offered($townA));

        $courier->syncLocalities([$townB->id]);
        $this->assertSame([], $this->offered($townA));
        $this->assertSame(['our-courier'], $this->offered($townB));

        $courier->update(['price' => 1200]);
        $this->assertSame(1200, $this->option($townB, 'our-courier')->quote?->amount);

        $courier->update(['enabled' => false]);
        $this->assertSame([], $this->offered($townB));
    }
}
