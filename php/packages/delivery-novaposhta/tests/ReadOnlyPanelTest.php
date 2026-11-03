<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryShipment;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\NovaPoshta\Types\BranchType;
use WebxUi\Delivery\Shipping\ShipmentStatus;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Localization\Locales;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * A site on somebody else's key (`read_only`, decision 19): the manager presses «Create waybill»
 * or «Cancel waybill» in the panel — the very route of the order's tab — and reads why nothing
 * happens, while not a single request reaches the carrier. This is how the lab's scenario (§13.3)
 * is checked: never by pressing the button on a client's key.
 */
final class ReadOnlyPanelTest extends TestCase
{
    private const string TOWN = '00065646-4079-11de-b509-001d92f78698';

    private const string BRANCH = '1ec09d5d-e1c2-11e3-8c4a-0050568002cf';

    private const string SENDER = '00000000-0000-4000-8000-000000000001';

    private const string REFUSAL = "This site uses the carrier's key read only: waybills are off.";

    /** While true the carrier answers with the recorded directory of Шумськ. */
    private bool $directory = false;

    /** @var list<string> every call besides the night's: «Model.method» */
    private array $sent = [];

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The panel and the orders: the route of the order's tab «Delivery».
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

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-delivery-novaposhta.read_only', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 10:00', 'Europe/Kyiv'));
        DeliveryCarrier::query()->create([
            'key' => NovaPoshtaCarrier::KEY,
            'enabled' => true,
            'credentials' => ['api_key' => self::KEY],
            'settings' => ['sender' => self::SENDER, 'contact' => self::SENDER, 'sender_city' => self::TOWN, 'sender_point' => self::BRANCH, 'payment_method' => 'Cash'],
        ]);
        Http::fake([self::URL => fn (Request $request) => $this->reply($request)]);
        $this->night();
        $this->actingAs(CmsUser::query()->create([
            'name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'correct-horse-battery',
            'is_super' => true,
            'is_active' => true,
        ]), 'cms');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function reply(Request $request): mixed
    {
        if ($this->directory) {
            $page = (string) ($request['methodProperties']['Page'] ?? '');

            if ($request['calledMethod'] === 'getWarehouses') {
                return Http::response(self::fixture($page === '1' ? 'Address.getWarehouses.small-city' : 'Address.getWarehouses.network-past-the-end'));
            }

            $towns = ['1' => 'Address.getCities.page-1', '2' => 'Address.getCities.page-2', '3' => 'Address.getCities.last-page'];

            return Http::response(self::fixture($towns[$page] ?? 'Address.getCities.past-the-end'));
        }

        $this->sent[] = $request['modelName'].'.'.$request['calledMethod'];

        return Http::response('', 500);
    }

    /** The towns and the points of Шумськ, as a night of the sync leaves them. */
    private function night(): void
    {
        $this->app->instance(NovaPoshtaCarrier::class, new NovaPoshtaCarrier(
            $this->app->make(Client::class),
            new AccountKey,
            new Pace(0),
            $this->app->make(Locales::class),
            pageSize: 20,
        ));
        $this->directory = true;
        $sync = $this->app->make(Synchronizer::class);
        $this->assertSame(RunStatus::Succeeded, $sync->run(NovaPoshtaCarrier::KEY, Directory::Localities, Trigger::Command)->status);
        $this->assertSame(RunStatus::Succeeded, $sync->run(NovaPoshtaCarrier::KEY, Directory::Points, Trigger::Command)->status);
        $this->directory = false;
    }

    /** An order to the branch of Шумськ, the recipient in Ukrainian letters: a waybill it could make. */
    private function order(): Order
    {
        $locality = (int) DeliveryCarrierLocality::query()->where('carrier', NovaPoshtaCarrier::KEY)->where('external_id', self::TOWN)->value('locality_id');
        $this->assertGreaterThan(0, $locality);

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
            'customer_phone' => '+38 (050) 123-45-67',
            'placed_at' => CarbonImmutable::now(),
            'delivery' => [
                'carrier' => NovaPoshtaCarrier::KEY,
                'type' => BranchType::KEY,
                'method' => 'novaposhta-branch',
                'payer' => 'recipient',
                'locality' => ['id' => $locality],
                'point' => ['external_id' => self::BRANCH],
            ],
        ])->save();

        return $order;
    }

    #[Test]
    public function the_manager_reads_that_waybills_are_off_and_nothing_reaches_the_carrier(): void
    {
        $order = $this->order();

        // The dry run still shows the request: it sends nothing anyway.
        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", ['dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.request.recipient.first_name', 'Олена')
            ->assertJsonPath('data.request.carrier_locality', self::TOWN);

        // The draft passes every check of §8 — the refusal is the key's, not the order's.
        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['waybill' => self::REFUSAL]);

        $this->assertSame(0, DeliveryShipment::query()->count());
        $this->assertSame([], $this->sent);
        $this->assertNoWritingInTheJournal();
    }

    #[Test]
    public function a_waybill_made_elsewhere_is_not_cancelled_from_here(): void
    {
        $order = $this->order();
        $shipment = DeliveryShipment::query()->create([
            'order_id' => $order->id,
            'carrier' => NovaPoshtaCarrier::KEY,
            'type' => BranchType::KEY,
            'method_key' => 'novaposhta-branch',
            'number' => '20450000000001',
            'payer' => 'recipient',
            'status' => ShipmentStatus::Created,
            'status_at' => CarbonImmutable::now(),
            'meta' => ['ref' => '00000000-0000-4000-8000-000000000106'],
        ]);

        $this->postJson("/api/cms/delivery/shipments/{$shipment->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['waybill' => self::REFUSAL]);

        $this->assertSame(ShipmentStatus::Created, $shipment->refresh()->status);
        $this->assertSame([], $this->sent);
        $this->assertNoWritingInTheJournal();
    }

    private function assertNoWritingInTheJournal(): void
    {
        $methods = array_column($this->journalEntries(), 'method');

        $this->assertSame([], array_values(array_filter($methods, static fn (string $method): bool => ! str_starts_with($method, 'get'))));
    }
}
