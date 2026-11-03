<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Delivery\Contracts\TracksShipments;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryShipment;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\NovaPoshta\Tracking\Tracking;
use WebxUi\Delivery\Shipping\Shipment;
use WebxUi\Delivery\Shipping\Shipments;
use WebxUi\Delivery\Shipping\ShipmentStatus;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The tracking of Nova Poshta (§9, decisions 9, 16): one `getStatusDocuments` for a batch of up
 * to a hundred, the carrier's codes by the table of §9.2, and — through the base's own
 * `Shipments::track()` — «received» marking the order once and a final status ending the asking.
 *
 * All on made-up answers: the numbers of a client's parcels are its buyers' data, so the tracking
 * is never asked on a client's key; a read-only key tracks nothing at all.
 */
final class TrackingTest extends TestCase
{
    /** @var list<array<string, mixed>> the `methodProperties` of every call */
    private array $sent = [];

    /** @var array<int|string, array{0: string, 1?: string, 2?: string}> number → [code, text, time]; PHP keeps a numeric one as int */
    private array $statuses = [];

    /** Answer with the recorded-after-the-documentation file instead of {@see $statuses}. */
    private bool $documented = false;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The orders, for the milestone «received» the base sets.
        return [
            CompositionsServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
            OrdersServiceProvider::class,
            ...parent::getPackageProviders($app),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 10:00', 'Europe/Kyiv'));
        DeliveryCarrier::query()->create(['key' => NovaPoshtaCarrier::KEY, 'enabled' => true, 'credentials' => ['api_key' => self::KEY]]);
        Http::fake([self::URL => fn (Request $request) => $this->reply($request)]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function reply(Request $request): mixed
    {
        $this->assertSame('TrackingDocument', $request['modelName']);
        $this->assertSame('getStatusDocuments', $request['calledMethod']);
        $this->sent[] = $request['methodProperties'];

        if ($this->documented) {
            return Http::response(self::fixture('TrackingDocument.getStatusDocuments.documented-statuses'));
        }

        $data = [];

        foreach ($request['methodProperties']['Documents'] as $document) {
            $status = $this->statuses[$document['DocumentNumber']] ?? null;

            if ($status !== null) {
                $data[] = ['Number' => $document['DocumentNumber'], 'StatusCode' => $status[0], 'Status' => $status[1] ?? 'Status '.$status[0], 'TrackingUpdateDate' => $status[2] ?? ''];
            }
        }

        return Http::response(self::answer($data));
    }

    private function shipment(int $id, ?string $number, ShipmentStatus $status = ShipmentStatus::Created): Shipment
    {
        return new Shipment($id, 1, NovaPoshtaCarrier::KEY, 'novaposhta.branch', 'novaposhta-branch', $number, 'recipient', $status);
    }

    private function tracking(): Tracking
    {
        return $this->app->make(Tracking::class);
    }

    private function order(): Order
    {
        $order = new Order;
        $order->forceFill([
            'number' => 'T-1',
            'locale' => 'en',
            'currency' => 'UAH',
            'status_id' => (int) DB::table('order_statuses')->orderBy('id')->value('id'),
            'subtotal' => 100000,
            'adjustments_total' => 0,
            'total' => 100000,
            'tenders_total' => 0,
            'payable' => 100000,
            'customer_first_name' => 'Олена',
            'customer_last_name' => 'Приклад',
            'customer_phone' => '+800 1234 5678',
            'placed_at' => CarbonImmutable::now()->subDays(3),
        ])->save();

        return $order;
    }

    private function row(Order $order, string $number): DeliveryShipment
    {
        return DeliveryShipment::query()->create([
            'order_id' => $order->id,
            'carrier' => NovaPoshtaCarrier::KEY,
            'type' => 'novaposhta.branch',
            'method_key' => 'novaposhta-branch',
            'number' => $number,
            'payer' => 'recipient',
            'status' => ShipmentStatus::Created,
            'status_at' => CarbonImmutable::now(),
            'next_check_at' => CarbonImmutable::now(),
        ]);
    }

    #[Test]
    public function the_three_types_track_their_parcels(): void
    {
        $types = $this->app->make(MethodTypes::class);

        foreach (['novaposhta.branch', 'novaposhta.locker', 'novaposhta.courier'] as $key) {
            $this->assertInstanceOf(TracksShipments::class, $types->get($key), $key);
        }
    }

    #[Test]
    public function the_codes_of_the_carrier_follow_the_table_of_9_2(): void
    {
        $table = [
            '1' => ['created', false], '2' => ['cancelled', true], '12' => ['created', false],
            '4' => ['in_transit', false], '41' => ['in_transit', false], '5' => ['in_transit', false], '6' => ['in_transit', false],
            '7' => ['at_point', false], '8' => ['at_point', false],
            '9' => ['received', true], '10' => ['received', true], '11' => ['received', true], '106' => ['received', true],
            '101' => ['out_for_delivery', false], '111' => ['out_for_delivery', false],
            '104' => ['in_transit', false], '112' => ['in_transit', false],
            '102' => ['refused', false], '103' => ['refused', false],
            '105' => ['returning', false],
        ];
        $this->assertEqualsCanonicalizing(array_map('strval', array_keys($table)), array_map('strval', array_keys(Tracking::STATUSES)));

        foreach ($table as $code => [$status, $final]) {
            $state = $this->tracking()->state($this->shipment(1, '1'), ['Number' => '1', 'StatusCode' => (string) $code, 'Status' => "Words {$code}"]);

            $this->assertNotNull($state, (string) $code);
            $this->assertSame($status, $state->status->value, "code {$code}");
            $this->assertSame($final, $state->final, "code {$code}");
            $this->assertSame("Words {$code}", $state->text);
        }

        // «Not found» is not an answer: the base asks again later.
        $this->assertNull($this->tracking()->state($this->shipment(1, '1'), ['Number' => '1', 'StatusCode' => '3', 'Status' => 'Not found']));
        // A return is never recognised (decision 16): nothing the carrier says becomes «returned».
        $this->assertNotContains(ShipmentStatus::Returned, Tracking::STATUSES);
    }

    #[Test]
    public function a_new_code_keeps_the_status_and_says_the_carriers_words(): void
    {
        $log = Log::spy();

        $state = $this->tracking()->state($this->shipment(7, '1', ShipmentStatus::AtPoint), ['Number' => '1', 'StatusCode' => '999', 'Status' => 'Something new']);

        $this->assertNotNull($state);
        $this->assertSame(ShipmentStatus::AtPoint, $state->status);
        $this->assertFalse($state->final);
        $this->assertSame('Something new', $state->text);
        $log->shouldHaveReceived('warning')->once()->withArgs(static fn (string $line): bool => str_contains($line, 'unknown tracking status code 999') && str_contains($line, 'shipment 7'));
    }

    #[Test]
    public function a_batch_of_a_hundred_is_one_request(): void
    {
        $shipments = [];

        for ($i = 1; $i <= 100; $i++) {
            $shipments[] = $this->shipment($i, (string) (20450000000000 + $i));
            $this->statuses[(string) (20450000000000 + $i)] = ['5'];
        }

        // One without a number yet is not asked about.
        $shipments[] = $this->shipment(101, null);

        $states = $this->tracking()->track($shipments);

        $this->assertCount(1, $this->sent);
        $this->assertCount(100, $this->sent[0]['Documents']);
        $this->assertSame(['DocumentNumber' => '20450000000001'], $this->sent[0]['Documents'][0]);
        $this->assertCount(100, $states);
        $this->assertArrayNotHasKey(101, $states);
        $this->assertSame(ShipmentStatus::InTransit, $states[1]->status);

        // The journal names the one reading method, never the numbers.
        $entries = $this->journalEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(['TrackingDocument', 'getStatusDocuments', 'ok'], [$entries[0]['model'], $entries[0]['method'], $entries[0]['outcome']]);
        $this->assertStringNotContainsString('20450000000001', (string) file_get_contents($this->journal));
    }

    #[Test]
    public function more_than_a_hundred_goes_a_hundred_at_a_time(): void
    {
        $shipments = [];

        for ($i = 1; $i <= 150; $i++) {
            $shipments[] = $this->shipment($i, (string) (20450000000000 + $i));
        }

        $this->assertSame([], $this->tracking()->track($shipments));
        $this->assertSame([100, 50], array_map(static fn (array $sent): int => count($sent['Documents']), $this->sent));
    }

    #[Test]
    public function the_documented_answer_is_read(): void
    {
        $this->documented = true;
        $shipments = [];

        foreach ([1, 2, 3, 4, 5] as $i) {
            $shipments[] = $this->shipment($i, '2045000000000'.$i, ShipmentStatus::InTransit);
        }

        $states = $this->tracking()->track($shipments);

        $this->assertSame([1, 2, 3, 5], array_keys($states));
        $this->assertSame(ShipmentStatus::Created, $states[1]->status);
        $this->assertSame(ShipmentStatus::AtPoint, $states[2]->status);
        $this->assertSame('Прибув у відділення', $states[2]->text);
        $this->assertSame('2026-11-02 08:30', CarbonImmutable::instance($states[2]->at)->setTimezone('Europe/Kyiv')->format('Y-m-d H:i'));
        // «Received» takes the time it was handed over.
        $this->assertSame(ShipmentStatus::Received, $states[3]->status);
        $this->assertTrue($states[3]->final);
        $this->assertSame('2026-11-02 09:40', CarbonImmutable::instance($states[3]->at)->setTimezone('Europe/Kyiv')->format('Y-m-d H:i'));
        // A new code: the status it had.
        $this->assertSame(ShipmentStatus::InTransit, $states[5]->status);
    }

    #[Test]
    public function received_marks_the_order_once_and_a_final_status_ends_the_asking(): void
    {
        $order = $this->order();
        $shipment = $this->row($order, '20450000000003');
        $cancelled = $this->row($order, '20450000000009');
        $shipments = $this->app->make(Shipments::class);

        $this->statuses['20450000000003'] = ['7', 'Прибув у відділення'];
        $this->statuses['20450000000009'] = ['2', 'Видалено'];
        $this->assertSame(2, $shipments->track());
        $this->assertCount(1, $this->sent);
        $this->assertCount(2, $this->sent[0]['Documents']);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::AtPoint, $shipment->status);
        $this->assertFalse($shipment->final);
        // At the point: asked again in half an hour.
        $this->assertEqualsWithDelta(CarbonImmutable::now()->addMinutes(30)->getTimestamp(), $shipment->next_check_at?->getTimestamp(), 2);
        $this->assertTrue($cancelled->refresh()->final);
        $this->assertNull($cancelled->next_check_at);

        $later = CarbonImmutable::now()->addHour();
        $this->statuses['20450000000003'] = ['9', 'Відправлення отримано', '2026-11-02 10:50:00'];
        $this->assertSame(1, $shipments->track(now: $later));
        // The cancelled one is not asked about again.
        $this->assertSame([['DocumentNumber' => '20450000000003']], $this->sent[1]['Documents']);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Received, $shipment->status);
        $this->assertTrue($shipment->final);
        $this->assertNull($shipment->next_check_at);
        $this->assertSame(['received', 'at_point'], array_column($shipment->events, 'status'));

        $milestones = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Received->value)->get();
        $this->assertCount(1, $milestones);
        $this->assertSame('delivery:novaposhta', $milestones[0]->source);
        $this->assertNotNull(Order::query()->findOrFail($order->id)->received_at);

        // Final: the schedule asks nobody, the carrier is not called, the milestone stays one.
        $this->assertSame(0, $shipments->track(now: $later->addDays(1)));
        $this->assertSame(0, $shipments->track([$shipment]));
        $this->assertCount(2, $this->sent);
        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Received->value)->count());
    }

    #[Test]
    public function a_parcel_the_carrier_does_not_know_is_asked_again_later(): void
    {
        $order = $this->order();
        $shipment = $this->row($order, '20450000000004');
        $this->statuses['20450000000004'] = ['3', 'Номер не знайдено'];

        $this->assertSame(0, $this->app->make(Shipments::class)->track());

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Created, $shipment->status);
        $this->assertFalse($shipment->final);
        $this->assertEqualsWithDelta(CarbonImmutable::now()->addHours(2)->getTimestamp(), $shipment->next_check_at?->getTimestamp(), 2);
    }

    #[Test]
    public function without_a_key_nothing_is_asked(): void
    {
        DeliveryCarrier::query()->whereKey(NovaPoshtaCarrier::KEY)->update(['credentials' => null]);

        try {
            $this->tracking()->track([$this->shipment(1, '20450000000001')]);
            $this->fail('No key, no tracking.');
        } catch (CarrierUnavailable) {
            $this->assertSame([], $this->sent);
        }
    }

    #[Test]
    public function a_read_only_key_tracks_nothing_unless_said_otherwise(): void
    {
        $this->statuses['20450000000001'] = ['9'];
        $parcel = [$this->shipment(1, '20450000000001')];

        $this->app['config']->set('webx-delivery-novaposhta.read_only', true);
        $this->app->forgetInstance(Tracking::class);
        $this->assertFalse($this->tracking()->enabled());
        $this->assertSame([], $this->tracking()->track($parcel));
        $this->assertSame([], $this->sent);
        $this->assertSame([], $this->journalEntries());

        // Switched off on any key.
        $this->app['config']->set('webx-delivery-novaposhta.read_only', false);
        $this->app['config']->set('webx-delivery-novaposhta.tracking', false);
        $this->app->forgetInstance(Tracking::class);
        $this->assertSame([], $this->tracking()->track($parcel));
        $this->assertSame([], $this->sent);

        // On by word: a read-only key of one's own account.
        $this->app['config']->set('webx-delivery-novaposhta.read_only', true);
        $this->app['config']->set('webx-delivery-novaposhta.tracking', true);
        $this->app->forgetInstance(Tracking::class);
        $this->assertCount(1, $this->tracking()->track($parcel));
        $this->assertCount(1, $this->sent);
    }
}
