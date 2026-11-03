<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Purchasables;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Delivery\Tests\Fakes\FakeBranchType;
use WebxUi\Delivery\Tests\Fakes\FakeCarrierA;
use WebxUi\Delivery\Tests\Fakes\FakeCourierType;
use WebxUi\Delivery\Tests\Fakes\FakeGoods;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The delivery step on a shop as the demo has it (§18): towns made by hand in `XX`, the shop's own
 * pick-up with two stores and its courier in Town A, and «carrier A» with branches and a courier
 * whose streets are in its directory — on a real cart the visitor's cookie opens.
 */
abstract class StepTestCase extends TestCase
{
    protected GeoLocality $townA;

    protected GeoLocality $townB;

    protected DeliveryMethod $pickup;

    protected DeliveryMethod $courier;

    protected Cart $basket;

    protected string $token;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Purchasables::class)->register('test.goods', new FakeGoods);
        $this->townA = $this->town('Town A');
        $this->townB = $this->town('Town B', 'Region B');
        $this->point($this->townA, 'store', ['titles' => ['en' => 'Store A'], 'number' => '1']);
        $this->point($this->townA, 'store', ['titles' => ['en' => 'Store B'], 'number' => '2', 'address' => ['en' => '2 Example street']]);
        $this->pickup = $this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store', 'titles' => ['en' => 'Pick up at a store']]);
        $this->courier = $this->method('our-courier', StoreCourier::KEY, ['price' => 500, 'titles' => ['en' => 'Our courier']]);
        $this->courier->syncLocalities([$this->townA->id]);
        $this->app->make(PopularLocalities::class)->set([$this->townB->id, $this->townA->id]);
        $added = $this->app->make(CartService::class)->add(null, [['type' => 'test.goods', 'ref' => 1]], 'en');
        $this->basket = $added->cart;
        $this->token = (string) $added->token;
    }

    /** The cookie that opens the cart, as the browser sends it. */
    protected function withCart(): static
    {
        // A JSON request of a test carries cookies only with credentials, as fetch does same-origin.
        return $this->withCredentials()->withCookie($this->app->make(CartCookie::class)->name(), $this->token);
    }

    /** «Carrier A» with branches in Town A and a courier whose streets the directory has there. */
    /** @param  class-string<Carrier>|Carrier  $carrier  «carrier A» as the test wants it: a plain one, or the directory of \WebxUi\Delivery\Testing\FakeCarrier */
    protected function carrierA(int $branches = 3, int $streets = 3, string|Carrier $carrier = FakeCarrierA::class): DeliveryCarrierLocality
    {
        $this->app->make(Carriers::class)->register($carrier);
        FakeBranchType::reset();
        $this->app->make(MethodTypes::class)->register(FakeBranchType::class);
        $this->app->make(MethodTypes::class)->register(FakeCourierType::class);
        DeliveryCarrier::query()->create(['key' => 'carrier-a', 'enabled' => true]);
        $binding = DeliveryCarrierLocality::query()->create([
            'carrier' => 'carrier-a',
            'external_id' => 'a-1',
            'locality_id' => $this->townA->id,
            'status' => 'matched',
            'method' => 'auto_exact',
            'record' => ['name' => 'Town A'],
            'features' => ['courier', 'branch'],
            'streets' => $streets,
        ]);

        // As the sync writes them: rows in batches, the search string built by the model's rule.
        foreach (array_chunk(range(1, $branches), 500) as $chunk) {
            DB::table('delivery_points')->insert(array_map(fn (int $i): array => [
                'carrier' => 'carrier-a',
                'external_id' => 'p-'.$i,
                'kind' => 'branch',
                'locality_id' => $this->townA->id,
                'carrier_locality_id' => $binding->id,
                'number' => (string) $i,
                'titles' => json_encode(['en' => 'Branch '.$i]),
                'address' => json_encode(['en' => $i.' Example street']),
                'schedule' => json_encode(['week' => ['mon' => [['09:00', '18:00']]]]),
                'search' => DeliveryPoint::searchOf((string) $i, ['en' => 'Branch '.$i], ['en' => $i.' Example street']),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], $chunk));
        }

        foreach (array_chunk(range(1, $streets), 500) as $chunk) {
            DB::table('delivery_streets')->insert(array_map(static fn (int $i): array => [
                'carrier' => 'carrier-a',
                'external_id' => 's-'.$i,
                'carrier_locality_id' => $binding->id,
                'kind' => 'street',
                'names' => json_encode(['en' => 'Elm '.$i]),
                'name_key' => 'elm '.$i,
                'created_at' => now(),
                'updated_at' => now(),
            ], $chunk));
        }

        $this->method('carrier-a-branch', 'carrier-a.branch', ['point_kind' => 'branch', 'titles' => ['en' => 'Carrier A branch']]);
        $this->method('carrier-a-courier', 'carrier-a.courier', ['titles' => ['en' => 'Carrier A courier']]);

        return $binding;
    }
}
