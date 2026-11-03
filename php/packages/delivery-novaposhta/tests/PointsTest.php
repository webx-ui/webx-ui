<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Checkout\StepView;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\Directories\Points;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\Panel\CarrierRows;
use WebxUi\Delivery\Points as BasePoints;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\PointRecord;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Localization\Locales;

/**
 * The points of `getWarehouses` (§6.3, decision 20) on the answers recorded on the key (stage 3):
 * a branch, a «Пункт приймання-видачі» and a locker are taken, the carrier's own drop-off points
 * are not, a locker for the residents of a block is taken and said and goes after the others; the
 * hours, the limits, the postcode and the coordinates of the town from its points.
 *
 * A closed branch, a point closed to choosing, a fulfilment centre and a 303 never came on the key:
 * those cases are recorded entries with a field changed — drafts after the documentation.
 */
final class PointsTest extends TestCase
{
    /** Шумськ: the small town of the recorded points, on the last recorded page of towns. */
    private const string TOWN = '00065646-4079-11de-b509-001d92f78698';

    private const string BRANCH = '1ec09d5d-e1c2-11e3-8c4a-0050568002cf';

    private const string RESIDENTS = '1b8378ea-d1ad-11ef-98f8-d4f5ef0df2b9';

    private const string DROP_OFF = 'afc7e975-b93b-11ef-98f8-d4f5ef0df2b9';

    /**
     * A Ukrainian site: the titles and addresses in Ukrainian first.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-localization.locales', [['code' => 'uk', 'default' => true], ['code' => 'en']]);
    }

    private function carrier(int $pageSize = 20): NovaPoshtaCarrier
    {
        return new NovaPoshtaCarrier(
            $this->app->make(Client::class),
            new AccountKey,
            new Pace(0),
            $this->app->make(Locales::class),
            pageSize: $pageSize,
        );
    }

    /** @return list<array<string, mixed>> */
    private static function entries(string $fixture): array
    {
        /** @var list<array<string, mixed>> $data */
        $data = self::fixture($fixture)['data'];

        return $data;
    }

    /**
     * A recorded entry by its ref, with fields changed.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function entry(string $ref, array $fields = [], string $fixture = 'Address.getWarehouses.small-city'): array
    {
        foreach (self::entries($fixture) as $entry) {
            if ($entry['Ref'] === $ref) {
                return [...$entry, ...$fields];
            }
        }

        self::fail("No entry {$ref} in {$fixture}.");
    }

    /** @return array<string, PointRecord> */
    private static function records(string $fixture, ?Points $points = null): array
    {
        $records = [];

        foreach (($points ?? new Points(['uk', 'en']))->records(self::entries($fixture)) as $record) {
            $records[$record->externalId] = $record;
        }

        return $records;
    }

    /** The towns from the recorded pages, then the points of the small town: a night of the carrier. */
    private function night(): DeliverySyncRun
    {
        $this->app->instance(NovaPoshtaCarrier::class, $this->carrier());
        DeliveryCarrier::query()->create(['key' => NovaPoshtaCarrier::KEY, 'enabled' => true, 'credentials' => ['api_key' => self::KEY]]);
        $towns = ['1' => 'Address.getCities.page-1', '2' => 'Address.getCities.page-2', '3' => 'Address.getCities.last-page'];

        Http::fake([self::URL => static function (Request $request) use ($towns) {
            $page = (string) ($request['methodProperties']['Page'] ?? '');

            if ($request['calledMethod'] === 'getWarehouses') {
                return Http::response(self::fixture($page === '1' ? 'Address.getWarehouses.small-city' : 'Address.getWarehouses.network-past-the-end'));
            }

            return Http::response(self::fixture($towns[$page] ?? 'Address.getCities.past-the-end'));
        }]);

        $sync = $this->app->make(Synchronizer::class);
        $localities = $sync->run(NovaPoshtaCarrier::KEY, Directory::Localities, Trigger::Command);
        $this->assertSame(RunStatus::Succeeded, $localities->status, (string) $localities->error);
        $points = $sync->run(NovaPoshtaCarrier::KEY, Directory::Points, Trigger::Command);
        $this->assertSame(RunStatus::Succeeded, $points->status, (string) $points->error);

        return $points;
    }

    #[Test]
    public function a_branch_and_lockers_are_taken_with_their_hours_limits_and_place(): void
    {
        $records = self::records('Address.getWarehouses.small-city');

        // Seven points: a branch, five lockers, one drop-off point of the carrier's own.
        $this->assertCount(6, $records);
        $this->assertArrayNotHasKey(self::DROP_OFF, $records);

        $branch = $records[self::BRANCH];
        $this->assertSame(['branch', self::TOWN, '1'], [$branch->kind, $branch->localityExternalId, $branch->number]);
        $this->assertSame('Шумськ, Українська, 36', $branch->address);
        $this->assertSame(['uk' => 'Шумськ, Українська, 36'], $branch->addresses);
        $this->assertSame(['uk' => 'Відділення №1: вул. Українська, 36'], $branch->titles);
        $this->assertSame('47101', $branch->postcode);
        $this->assertSame([50.121543, 26.114394], [$branch->lat, $branch->lng]);
        $this->assertNull($branch->phone);
        $this->assertTrue($branch->active);
        $this->assertNull($branch->restriction);
        $this->assertSame(['category' => 'Branch', 'card' => true, 'status' => 'Working'], array_intersect_key($branch->meta, ['category' => 1, 'card' => 1, 'status' => 1]));

        // The hours of Ukraine; Saturday and Sunday shorter.
        $this->assertSame('Europe/Kyiv', $branch->schedule?->timezone);
        $this->assertSame([['08:00', '19:00']], $branch->schedule->week['mon']);
        $this->assertSame([['09:00', '18:00']], $branch->schedule->week['sun']);

        // A place of a cargo branch takes 1 100 kg; it receives 220 × 220 × 600 cm.
        $this->assertSame(['max_weight_g' => 1_100_000, 'max_length_mm' => 6000, 'max_width_mm' => 2200, 'max_height_mm' => 2200], $branch->limits?->toArray());

        // A locker around the clock — «00:01-23:59» — takes 20 kg of the whole point, 40 × 30 × 60 cm.
        $locker = $records['5183bf05-1109-11ef-98f8-d4f5ef0df2b9'];
        $this->assertSame(['locker', '42040'], [$locker->kind, $locker->number]);
        $this->assertSame([['00:00', '24:00']], $locker->schedule?->week['wed']);
        $this->assertSame(['max_weight_g' => 20_000, 'max_length_mm' => 600, 'max_width_mm' => 400, 'max_height_mm' => 300], $locker->limits?->toArray());
        $this->assertSame([['09:00', '21:00']], $records['af9e6a76-c6d9-11f0-98dc-8c8474c74af1']->schedule?->week['mon']);
    }

    #[Test]
    public function a_locker_for_the_residents_of_a_block_is_taken_and_marked(): void
    {
        $records = self::records('Address.getWarehouses.small-city');

        $this->assertSame('locker', $records[self::RESIDENTS]->kind);
        $this->assertSame('residents', $records[self::RESIDENTS]->restriction);
        $this->assertSame('ForResidentsOfEntrance', $records[self::RESIDENTS]->meta['post_machine_type'] ?? null);
        $this->assertSame([null], array_values(array_unique(array_map(
            static fn (PointRecord $record): ?string => $record->restriction,
            array_filter($records, static fn (PointRecord $record): bool => $record->externalId !== self::RESIDENTS),
        ))));

        // Across the network: every one of them kept, none of the drop-off points.
        $points = new Points(['uk']);
        $network = self::records('Address.getWarehouses.network-page-1', $points);
        $this->assertCount(7, array_filter($network, static fn (PointRecord $record): bool => $record->restriction === 'residents'));
        $this->assertSame([Points::SKIP_KIND => ['bb66686d-f2b4-11ef-98f8-d4f5ef0df2b9', '041e063c-a8a8-11ef-98f8-d4f5ef0df2b9']], $points->skipped());
    }

    #[Test]
    public function a_pick_up_point_is_a_branch_and_an_empty_translation_is_left_to_the_main_one(): void
    {
        $store = self::records('Address.getWarehouses.network-page-1', new Points(['uk', 'ru']))['f220c0ef-fd3c-11ed-9eb1-d4f5ef0df2b8'];

        $this->assertSame(['branch', '1', '09012'], [$store->kind, $store->number, $store->postcode]);
        // `ShortAddressRu` is empty on the key: the Russian address is not invented.
        $this->assertSame(['uk' => 'Миньківці, Квітнева, 15А'], $store->addresses);
        $this->assertSame([], $store->schedule?->week['sun']);
        $this->assertSame(30_000, $store->limits?->maxWeightG);
    }

    #[Test]
    public function the_carriers_own_points_and_those_closed_to_choosing_are_left_out_and_counted(): void
    {
        $points = new Points(['uk']);

        $records = $points->records([
            self::entry(self::BRANCH),
            self::entry(self::DROP_OFF),
            // Drafts: not met on the key.
            self::entry(self::BRANCH, ['Ref' => 'fulfilment', 'CategoryOfWarehouse' => 'Fulfillment']),
            self::entry(self::BRANCH, ['Ref' => 'denied', 'DenyToSelect' => '1']),
            self::entry(self::RESIDENTS, ['Ref' => 'denied-locker', 'DenyToSelect' => '1']),
            self::entry(self::BRANCH, ['Ref' => 'no-town', 'CityRef' => '']),
        ]);

        $this->assertSame([self::BRANCH], array_map(static fn (PointRecord $record): string => $record->externalId, $records));
        $this->assertSame([
            Points::SKIP_KIND => [self::DROP_OFF, 'fulfilment'],
            Points::SKIP_DENIED => ['denied', 'denied-locker'],
            Points::SKIP_INVALID => ['no-town'],
        ], $points->skipped());
    }

    #[Test]
    public function a_closed_branch_is_inactive_and_odd_hours_are_said_as_written(): void
    {
        // Draft: on the key only `Working` came.
        $closed = (new Points(['uk']))->record(self::entry(self::BRANCH, [
            'WarehouseStatus' => 'NotWorking',
            'PostalCodeUA' => '7850',
            'ShortAddress' => '',
            'ShortAddressRu' => '',
            'Schedule' => ['Monday' => '08:00-13:00, 14:00-19:00', 'Tuesday' => '-'],
        ]));

        $this->assertNotNull($closed);
        $this->assertFalse($closed->active);
        $this->assertSame('NotWorking', $closed->meta['status'] ?? null);
        // Four digits lost their leading zero.
        $this->assertSame('07850', $closed->postcode);
        // No short address: the title carries it.
        $this->assertSame('Відділення №1: вул. Українська, 36', $closed->address);
        $this->assertNull($closed->schedule?->week['mon']);
        $this->assertSame([], $closed->schedule?->week['tue']);
        $this->assertSame(['uk' => 'Monday: 08:00-13:00, 14:00-19:00'], $closed->schedule->text);
    }

    #[Test]
    public function the_whole_network_is_walked_by_pages_and_a_303_to_a_file_is_followed(): void
    {
        $file = 'https://cache.novaposhta.test/warehouses/page-1.json';
        Http::fake([
            self::URL => Http::sequence()
                ->push('', 303, ['Location' => $file])
                ->push(self::fixture('Address.getWarehouses.network-past-the-end')),
            $file => Http::response(self::fixture('Address.getWarehouses.network-page-1')),
        ]);
        $carrier = new NovaPoshtaCarrier($this->app->make(Client::class), new AccountKey(self::KEY), new Pace(0), $this->app->make(Locales::class), pageSize: 20);

        $first = $carrier->pull(Directory::Points, null);
        $past = $carrier->pull(Directory::Points, '1');

        // 20 entries: 2 drop-off points left out, the rest taken; a full page asks for the next.
        $this->assertCount(18, $first->records);
        $this->assertSame('1', $first->next);
        $this->assertSame([Points::SKIP_KIND => ['bb66686d-f2b4-11ef-98f8-d4f5ef0df2b9', '041e063c-a8a8-11ef-98f8-d4f5ef0df2b9']], $first->skipped);
        $this->assertSame([[], null], [$past->records, $past->next]);

        $sent = [];
        Http::assertSent(static function (Request $request) use (&$sent, $file): bool {
            if ($request->url() !== $file) {
                $sent[] = [$request['calledMethod'], $request['methodProperties']];
            }

            return true;
        });
        // The whole network: no `CityRef`.
        $this->assertSame([['getWarehouses', ['Page' => '1', 'Limit' => '20']], ['getWarehouses', ['Page' => '2', 'Limit' => '20']]], $sent);
    }

    #[Test]
    public function after_a_night_the_town_has_its_points_and_lies_where_they_are(): void
    {
        $run = $this->night();

        $this->assertSame([1, 6, 6], [$run->pages, $run->seen, $run->created]);
        $this->assertSame(1, $run->warnings['skipped'][Points::SKIP_KIND]['count'] ?? null);

        $binding = DeliveryCarrierLocality::query()->where('carrier', NovaPoshtaCarrier::KEY)->where('external_id', self::TOWN)->firstOrFail();
        $town = GeoLocality::query()->findOrFail($binding->locality_id);
        $this->assertSame(6, DB::table('delivery_points')->where('locality_id', $town->id)->count());
        $this->assertSame('residents', DB::table('delivery_points')->where('external_id', self::RESIDENTS)->value('restriction'));

        // The middle of the six points taken: the town had no coordinates from `getCities`.
        $taken = array_filter(self::entries('Address.getWarehouses.small-city'), static fn (array $entry): bool => $entry['Ref'] !== self::DROP_OFF);
        $this->assertEqualsWithDelta(array_sum(array_map(static fn (array $entry): float => round((float) $entry['Latitude'], 6), $taken)) / 6, $town->lat, 0.000002);
        $this->assertEqualsWithDelta(array_sum(array_map(static fn (array $entry): float => round((float) $entry['Longitude'], 6), $taken)) / 6, $town->lng, 0.000002);
        // A town without points stays without coordinates.
        $this->assertSame(1, GeoLocality::query()->whereNotNull('lat')->count());

        // Only reading went to the carrier.
        $this->assertSame(['getCities', 'getWarehouses'], array_values(array_unique(array_column($this->journalEntries(), 'method'))));
    }

    #[Test]
    public function the_lockers_of_a_town_put_the_one_for_residents_last_and_say_so(): void
    {
        $this->night();
        $town = (int) DeliveryCarrierLocality::query()->where('carrier', NovaPoshtaCarrier::KEY)->where('external_id', self::TOWN)->value('locality_id');
        $points = $this->app->make(BasePoints::class);

        $lockers = $points->page(NovaPoshtaCarrier::KEY, 'locker', $town, '', 1, 'uk');

        $this->assertSame(['42040', '56207', '59414', '60212', '49657'], array_column($lockers['points'], 'number'));
        $last = StepView::pointOption($lockers['points'][4]);
        $this->assertStringEndsWith('Лише для мешканців будинку', (string) $last['hint']);
        $this->assertStringNotContainsString('мешканців', (string) StepView::pointOption($lockers['points'][0])['hint']);

        // Its number typed in full brings it first.
        $this->assertSame('49657', $points->page(NovaPoshtaCarrier::KEY, 'locker', $town, '49657', 1, 'uk')['points'][0]['number']);
        // A word of a street finds it among the others, still after them.
        $this->assertSame(['42040', '59414', '49657'], array_column($points->page(NovaPoshtaCarrier::KEY, 'locker', $town, 'Українська', 1, 'uk')['points'], 'number'));

        // The branch is a kind of its own.
        $this->assertSame(['1'], array_column($points->page(NovaPoshtaCarrier::KEY, 'branch', $town, '', 1, 'uk')['points'], 'number'));
    }

    #[Test]
    public function a_points_line_does_not_repeat_the_street_its_name_already_says(): void
    {
        $this->night();
        $town = (int) DeliveryCarrierLocality::query()->where('carrier', NovaPoshtaCarrier::KEY)->where('external_id', self::TOWN)->value('locality_id');
        $branch = $this->app->make(BasePoints::class)->page(NovaPoshtaCarrier::KEY, 'branch', $town, '', 1, 'uk')['points'][0];

        $this->assertStringContainsString(':', $branch['title']);
        $this->assertNotSame('', $branch['address']);
        $this->assertNull($branch['line']);
        // The checkout's option: today's hours stay, the address repeated after the name does not.
        $this->assertStringNotContainsString($branch['address'], (string) StepView::pointOption($branch)['hint']);

        // A name without the street gets the town only.
        $carrier = $this->app->make(NovaPoshtaCarrier::class);
        $this->assertSame('Town A', $carrier->pointLine('Branch 1', 'Town A, Main St., 1'));
        $this->assertNull($carrier->pointLine('Branch 1: Main St., 1', 'Town A, Main St., 1'));
    }

    #[Test]
    public function the_points_left_out_are_said_in_words_on_the_screen_of_the_carriers(): void
    {
        $this->night();
        $rows = $this->app->make(CarrierRows::class);
        $carrier = $this->app->make(NovaPoshtaCarrier::class);

        foreach (['uk' => 'Пропущено службових точок: 1', 'en' => 'Skipped, service points of the carrier: 1'] as $locale => $words) {
            $directories = $rows->summary($carrier, $locale)['directories'];
            $this->assertIsArray($directories);
            $this->assertSame([['key' => 'skipped.'.Points::SKIP_KIND, 'count' => 1, 'label' => $words]], array_column($directories, 'remarks', 'directory')['points'] ?? null);
        }

        foreach (['novaposhta_service_point', 'novaposhta_deny_to_select', 'novaposhta_invalid_point'] as $reason) {
            $this->assertStringEndsWith(': 3', (string) $carrier->skipReason($reason, 3, 'uk'));
        }

        $this->assertNull($carrier->skipReason('something_new', 3, 'uk'));
    }
}
