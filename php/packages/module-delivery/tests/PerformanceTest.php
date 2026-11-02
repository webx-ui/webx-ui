<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Delivery\Testing\FakeCarrier;

/**
 * The budget of queries of the step (§17), warm, on «carrier A» with 3 000 branches and 3 000
 * streets in one town besides the shop's own methods. The count does not grow with the volume:
 * every read is one indexed query a page (the plans on MariaDB with 30 000 points and 300 000
 * streets — `SyncVolumeTest` and the `EXPLAIN`s of stages 5 and 6). «+ the cart» is what opening
 * and counting the visitor's cart takes by itself.
 *
 * The carrier is `FakeCarrier`, locked: any `pull()` throws — no request of the step calls the
 * carrier for its directories, and none queues a job (decision 38).
 */
final class PerformanceTest extends StepTestCase
{
    private FakeCarrier $carrier;

    private int $branch;

    private int $courierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrier = (new FakeCarrier)->lock();
        $this->carrierA(branches: 3000, streets: 3000, carrier: $this->carrier);
        $this->branch = (int) DeliveryMethod::query()->where('key', 'carrier-a-branch')->value('id');
        $this->courierA = (int) DeliveryMethod::query()->where('key', 'carrier-a-courier')->value('id');
        Queue::fake();

        // What the visitor's cart costs by itself, opened by its cookie and counted for the checkout.
        Route::middleware('web')->get('/test/cart', static function (): string {
            $handle = app(CartHandle::class);
            $cart = $handle->cart();
            app(Calculator::class)->calculate($cart, 'en', Purpose::Checkout);

            return 'ok';
        });
    }

    /** @return array{0: TestResponse<Response>, 1: list<string>} */
    private function measure(callable $request): array
    {
        // Warm: the caches of the groups and the throttle are filled by a first request.
        $request();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $request();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return [$response, $queries];
    }

    private function cartCost(): int
    {
        return count($this->measure(fn () => $this->withCart()->get('/test/cart'))[1]);
    }

    private function choose(int $method): void
    {
        DeliverySelection::query()->updateOrCreate(['cart_id' => $this->basket->id], ['country_code' => 'XX', 'locality_id' => $this->townA->id, 'method_id' => $method]);
    }

    #[Test]
    public function the_suggestions_of_towns_are_two_queries(): void
    {
        [$response, $queries] = $this->measure(fn () => $this->getJson('/api/delivery/localities?country=XX&q=town'));

        $response->assertOk();
        $this->assertLessThanOrEqual(2, count($queries), json_encode($queries));
    }

    #[Test]
    public function the_methods_of_a_town_are_four_queries_over_the_carts(): void
    {
        $this->choose($this->branch);
        $cart = $this->cartCost();

        [$response, $queries] = $this->measure(fn () => $this->withCart()->getJson("/api/delivery/methods?locality={$this->townA->id}"));

        $response->assertOk()->assertJsonCount(4, 'methods');
        $this->assertLessThanOrEqual(4 + $cart, count($queries), "cart {$cart}: ".json_encode($queries));
    }

    #[Test]
    public function a_page_of_points_searched_is_two_queries_and_the_first_page_comes_from_the_cache(): void
    {
        [$response, $queries] = $this->measure(fn () => $this->getJson("/api/delivery/points?method=carrier-a-branch&locality={$this->townA->id}&q=1234"));
        $response->assertOk()->assertJsonPath('options.0.label', 'Branch 1234');
        $this->assertLessThanOrEqual(2, count($queries), json_encode($queries));

        [$response, $queries] = $this->measure(fn () => $this->getJson("/api/delivery/points?method=carrier-a-branch&locality={$this->townA->id}"));
        $response->assertOk()->assertJsonCount(20, 'options')->assertJsonPath('total', 3000);
        $this->assertLessThanOrEqual(1, count($queries), json_encode($queries));
    }

    #[Test]
    public function the_suggestions_of_streets_are_two_queries(): void
    {
        [$response, $queries] = $this->measure(fn () => $this->getJson("/api/delivery/streets?method=carrier-a-courier&locality={$this->townA->id}&q=elm 12"));
        $response->assertOk();
        $this->assertLessThanOrEqual(2, count($queries), json_encode($queries));

        [, $queries] = $this->measure(fn () => $this->getJson("/api/delivery/streets?method=our-courier&locality={$this->townA->id}&q=elm 12"));
        $this->assertLessThanOrEqual(2, count($queries), json_encode($queries));
    }

    #[Test]
    public function writing_the_choice_is_six_queries_over_the_carts(): void
    {
        $this->choose($this->branch);
        $cart = $this->cartCost();
        $point = (int) DB::table('delivery_points')->where('carrier', 'carrier-a')->where('number', '777')->value('id');

        [$response, $queries] = $this->measure(fn () => $this->withCart()->putJson('/api/delivery/selection', ['method' => $this->branch, 'point' => $point]));
        $response->assertOk()->assertJsonPath('selection.point', $point);
        $this->assertLessThanOrEqual(6 + $cart, count($queries), "cart {$cart}: ".json_encode($queries));

        [$response, $queries] = $this->measure(fn () => $this->withCart()->putJson('/api/delivery/selection', ['method' => $this->courierA, 'address' => ['street_id' => 's-12', 'house' => '1']]));
        $response->assertOk()->assertJsonPath('selection.address.street', 'Elm 12 street');
        $this->assertLessThanOrEqual(6 + $cart, count($queries), "cart {$cart}: ".json_encode($queries));
    }

    #[Test]
    public function no_request_of_the_step_calls_the_carrier_or_queues_a_job(): void
    {
        $this->choose($this->courierA);
        $town = $this->townA->id;

        $this->getJson('/api/delivery/countries')->assertOk();
        $this->getJson('/api/delivery/localities?country=XX&q=t')->assertOk();
        $this->getJson("/api/delivery/points?method=carrier-a-branch&locality={$town}&q=5")->assertOk();
        $this->getJson("/api/delivery/streets?method=carrier-a-courier&locality={$town}&q=e")->assertOk();
        $this->withCart()->getJson("/api/delivery/methods?locality={$town}")->assertOk();
        $this->withCart()->withHeaders(['X-Webx-Delivery-View' => 'step'])->putJson('/api/delivery/selection', ['method' => $this->branch])->assertOk();
        $this->withCart()->getJson('/api/delivery/selection')->assertOk();

        $this->assertSame([], $this->carrier->pulls);
        Queue::assertNothingPushed();
    }
}
