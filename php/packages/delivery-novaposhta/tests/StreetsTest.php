<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Purchasables;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\Directories\Streets;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\NovaPoshta\StreetsPass;
use WebxUi\Delivery\NovaPoshta\Tests\Fakes\FakeParcel;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\Page;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Localization\Locales;

/**
 * The streets of every town with a courier (§6.4): the towns come from the base, the streets of
 * each from `getStreet` with an empty search, page by page of 20 here. A pull goes across towns
 * until its budget of requests, its time or a page of the base is spent, and its cursor — the
 * town and its pages — is where the next one goes on.
 *
 * The towns: ten, a courier in every even one. Their streets: town 0 — 45 (three pages), town 2 —
 * none (a courier without streets), town 4 — 20 (a full page, then an empty one), town 6 — 3,
 * town 8 — the village recorded on the key (11, a lane, a line, a square).
 */
final class StreetsTest extends TestCase
{
    private const int TOWNS = 10;

    private const int LIMIT = 20;

    protected function setUp(): void
    {
        parent::setUp();

        DeliveryCarrier::query()->create(['key' => NovaPoshtaCarrier::KEY, 'enabled' => true, 'credentials' => ['api_key' => self::KEY]]);

        Http::fake([self::URL => static function (Request $request) {
            $properties = $request['methodProperties'];
            $page = (int) ($properties['Page'] ?? 1);

            if ($request['calledMethod'] === 'getCities') {
                $towns = $page === 1 ? array_map(self::town(...), range(0, self::TOWNS - 1)) : [];

                return Http::response(self::answer($towns, total: self::TOWNS));
            }

            return Http::response(self::streetsOf((string) $properties['CityRef'], $page));
        }]);

        $this->carrier();
        $run = $this->app->make(Synchronizer::class)->run(NovaPoshtaCarrier::KEY, Directory::Localities, Trigger::Command);
        $this->assertSame(RunStatus::Succeeded, $run->status, (string) $run->error);
    }

    private function carrier(StreetsPass $pass = new StreetsPass(seconds: 60.0)): NovaPoshtaCarrier
    {
        $carrier = new NovaPoshtaCarrier(
            $this->app->make(Client::class),
            new AccountKey,
            new Pace(0),
            $this->app->make(Locales::class),
            pageSize: self::LIMIT,
            streets: $pass,
        );
        $this->app->instance(NovaPoshtaCarrier::class, $carrier);

        return $carrier;
    }

    private static function ref(int $n): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $n);
    }

    /** @return array<string, mixed> */
    private static function town(int $n): array
    {
        return [
            'Description' => "Приклад {$n}",
            'Ref' => self::ref($n),
            'Delivery1' => $n % 2 === 0 ? '1' : '0', 'Delivery2' => '0', 'Delivery3' => '0', 'Delivery4' => '0',
            'Delivery5' => '0', 'Delivery6' => '0', 'Delivery7' => '0',
            'Area' => '00000000-0000-4000-9000-000000000001',
            'CityID' => (string) $n,
            'SettlementTypeDescription' => 'село',
            'AreaDescription' => 'Полтавська',
        ];
    }

    /** @return array<string, mixed> */
    private static function streetsOf(string $town, int $page): array
    {
        $n = (int) substr($town, -12);

        if ($n === 8) {
            return $page === 1 ? self::fixture('Address.getStreet.village-empty-find') : self::answer(total: 11);
        }

        $count = [0 => 45, 2 => 0, 4 => 20, 6 => 3][$n] ?? 0;
        $streets = [];

        for ($i = ($page - 1) * self::LIMIT; $i < min($page * self::LIMIT, $count); $i++) {
            $streets[] = [
                'Description' => "Прикладна {$i}",
                'Ref' => sprintf('00000000-0000-4000-b%03d-%012d', $n, $i),
                'StreetsTypeRef' => 'Street',
                'StreetsType' => 'вул.',
            ];
        }

        return self::answer($streets, total: $count);
    }

    /**
     * The towns and pages asked for, in order: «0:1», «0:2», …
     *
     * @return list<string>
     */
    private static function asked(): array
    {
        $asked = [];

        foreach (Http::recorded() as [$request]) {
            if ($request['calledMethod'] === 'getStreet') {
                $asked[] = ((int) substr((string) $request['methodProperties']['CityRef'], -12)).':'.$request['methodProperties']['Page'];
            }
        }

        return $asked;
    }

    /** @return list<string> */
    private static function ids(Page $page): array
    {
        return array_map(static fn (object $record): string => $record->externalId, $page->records);
    }

    #[Test]
    public function a_pull_goes_across_towns_and_its_cursor_says_where_it_stopped(): void
    {
        $carrier = $this->carrier(new StreetsPass(requests: 4, seconds: 60.0));
        $before = count(self::asked());

        // Town 0 in three pages, then town 2 with none: four requests, the budget.
        $first = $carrier->pull(Directory::Streets, null);
        $this->assertSame(['0:1', '0:2', '0:3', '2:1'], array_slice(self::asked(), $before));
        $this->assertCount(45, $first->records);
        $this->assertSame(self::ref(2).':0', $first->next);

        // On after town 2: town 4 full, its empty page 2, town 6, town 8 — the budget again.
        $second = $carrier->pull(Directory::Streets, $first->next);
        $this->assertSame(['0:1', '0:2', '0:3', '2:1', '4:1', '4:2', '6:1', '8:1'], array_slice(self::asked(), $before));
        $this->assertCount(20 + 3 + 11, $second->records);
        $this->assertSame(self::ref(8).':0', $second->next);

        // No town is left after town 8: the pass ends without asking the carrier.
        $last = $carrier->pull(Directory::Streets, $second->next);
        $this->assertSame([], $last->records);
        $this->assertNull($last->next);
        $this->assertCount(8, array_slice(self::asked(), $before));

        // The towns without a courier are never asked, every street is of its own town.
        foreach ([...$first->records, ...$second->records] as $record) {
            $this->assertSame(0, ((int) substr($record->localityExternalId, -12)) % 2);
        }

        $ids = [...self::ids($first), ...self::ids($second)];
        $this->assertSame($ids, array_values(array_unique($ids)));
    }

    #[Test]
    public function a_pull_stopped_inside_a_town_goes_on_with_its_next_page(): void
    {
        $carrier = $this->carrier(new StreetsPass(requests: 2, seconds: 60.0));
        $before = count(self::asked());

        $first = $carrier->pull(Directory::Streets, null);
        $this->assertSame(self::ref(0).':2', $first->next);
        $this->assertCount(40, $first->records);

        $second = $carrier->pull(Directory::Streets, $first->next);
        $this->assertSame(['0:1', '0:2', '0:3', '2:1'], array_slice(self::asked(), $before));
        $this->assertCount(5, $second->records);
        $this->assertSame(self::ref(2).':0', $second->next);

        // The same cursor gives the same page: the base repeats a failed one (§7.1).
        $this->assertEquals($second, $carrier->pull(Directory::Streets, $first->next));
    }

    #[Test]
    public function the_budget_of_a_pull_is_its_requests_and_its_time(): void
    {
        $this->carrier(new StreetsPass(seconds: 60.0))->pull(Directory::Streets, null);
        // The default budget of 20 is more than the whole pass: eight requests and the end.
        Http::assertSentCount(1 + 8);

        // No time left: one request a pull, and the pull goes on from it.
        $carrier = $this->carrier(new StreetsPass(seconds: 0.0));
        $page = $carrier->pull(Directory::Streets, null);
        Http::assertSentCount(1 + 8 + 1);
        $this->assertSame(self::ref(0).':1', $page->next);

        $this->assertSame(['getCities', 'getStreet'], array_values(array_unique(array_column($this->journalEntries(), 'method'))));
    }

    #[Test]
    public function a_budget_larger_than_the_pass_walks_it_in_one_pull(): void
    {
        $pages = [];
        $cursor = null;
        $carrier = $this->carrier(new StreetsPass(requests: 1000, seconds: 60.0));

        // 79 streets are far from a page of the base of 2 000: one pull and the end. The stop at a
        // page of the base is in PerformanceTest, by its 2 000.
        do {
            $page = $carrier->pull(Directory::Streets, $cursor);
            $pages[] = count($page->records);
            $cursor = $page->next;
        } while ($cursor !== null);

        $this->assertSame([45 + 20 + 3 + 11], $pages);
    }

    #[Test]
    public function a_sample_walks_only_the_first_towns_with_a_courier(): void
    {
        $carrier = $this->carrier(new StreetsPass(requests: 2, seconds: 60.0, sample: 2));
        $before = count(self::asked());
        $cursor = null;

        do {
            $cursor = $carrier->pull(Directory::Streets, $cursor)->next;
        } while ($cursor !== null);

        $this->assertSame(['0:1', '0:2', '0:3', '2:1'], array_slice(self::asked(), $before));
    }

    #[Test]
    public function a_cursor_of_another_shape_is_a_bad_answer_and_asks_nothing(): void
    {
        $before = count(Http::recorded());

        foreach (['3', 'town', ':1', self::ref(0).':x'] as $cursor) {
            try {
                $this->carrier()->pull(Directory::Streets, $cursor);
                $this->fail("«{$cursor}» was taken for a cursor of the streets.");
            } catch (CarrierUnavailable $failure) {
                $this->assertStringContainsString('cursor', $failure->getMessage());
            }
        }

        $this->assertCount($before, Http::recorded());
    }

    #[Test]
    public function the_kinds_are_the_bases_and_another_type_keeps_the_carriers_word(): void
    {
        $kinds = [];

        // Every street recorded on the key: streets, lanes, squares, lines and a boulevard.
        foreach (['village-empty-find', 'big-city-page-1', 'big-city-page-2', 'big-city-last-page'] as $name) {
            $entries = self::fixture('Address.getStreet.'.$name)['data'];
            $this->assertIsArray($entries);

            foreach (Streets::records(self::ref(8), $entries) as $record) {
                $kinds[$record->kind][] = $record->name;
            }
        }

        ksort($kinds);
        $this->assertSame(['lane', 'other', 'square', 'street'], array_keys($kinds));
        $this->assertCount(4 + 1, $kinds['other']);

        foreach ($kinds['other'] as $name) {
            $this->assertMatchesRegularExpression('/^\S.* \((лінія|бульвар)\)$/u', $name);
        }
        $this->assertSame(['street', 'avenue', 'lane', 'square', 'other', 'other'], array_map(
            static fn (string $type): string => Streets::record('T', ['Ref' => 'R', 'Description' => 'Приклад', 'StreetsTypeRef' => $type, 'StreetsType' => 'бульвар'])->kind ?? '-',
            ['Street', 'Prospectus', 'Lane', 'Square', 'Boulevard', 'Line'],
        ));
        $this->assertSame('Шевченка (бульвар)', Streets::record('T', ['Ref' => 'R', 'Description' => 'Шевченка', 'StreetsTypeRef' => 'Boulevard', 'StreetsType' => 'бульвар'])?->name);
        $this->assertSame('Шевченка', Streets::record('T', ['Ref' => 'R', 'Description' => 'Шевченка', 'StreetsTypeRef' => 'Street', 'StreetsType' => 'вул.'])?->name);
        $this->assertNull(Streets::record('T', ['Ref' => '', 'Description' => 'Шевченка']));
        $this->assertNull(Streets::record('T', ['Ref' => 'R', 'Description' => ' ']));
    }

    #[Test]
    public function a_pass_through_the_base_leaves_a_courier_without_streets_disabled(): void
    {
        $run = $this->app->make(Synchronizer::class)->run(NovaPoshtaCarrier::KEY, Directory::Streets, Trigger::Command);
        $this->assertInstanceOf(DeliverySyncRun::class, $run);
        $this->assertSame(RunStatus::Succeeded, $run->status, (string) $run->error);
        $this->assertSame(45 + 20 + 3 + 11, $run->seen);
        $this->assertSame(['count' => 1, 'ids' => [self::ref(2)]], $run->warnings[Synchronizer::NO_STREETS] ?? null);

        $streets = DeliveryCarrierLocality::query()->where('carrier', NovaPoshtaCarrier::KEY)->orderBy('external_id')->pluck('streets', 'external_id')->all();
        $this->assertSame([45, 0, 0, 0, 20, 0, 3, 0, 11, 0], array_values(array_map('intval', $streets)));

        // The village recorded on the key, written as the base keeps a street.
        $village = DeliveryCarrierLocality::query()->where('external_id', self::ref(8))->value('id');
        $this->assertSame(['lane' => 1, 'street' => 10], DB::table('delivery_streets')->where('carrier_locality_id', $village)
            ->selectRaw('kind, count(*) as n')->groupBy('kind')->orderBy('kind')->pluck('n', 'kind')
            ->map(static fn (mixed $n): int => (int) $n)->all());

        DeliveryMethod::query()->where('key', 'novaposhta-courier')->update(['enabled' => true]);
        $withStreets = (int) DeliveryCarrierLocality::query()->where('external_id', self::ref(0))->value('locality_id');
        $without = (int) DeliveryCarrierLocality::query()->where('external_id', self::ref(2))->value('locality_id');

        // The step of a real cart, opened by its cookie as the browser sends it.
        $this->app->make(Purchasables::class)->register('test.parcel', new FakeParcel);
        $token = (string) $this->app->make(CartService::class)->add(null, [['type' => 'test.parcel', 'ref' => 1]], 'en')->token;
        $step = fn (): static => $this->withCredentials()->withCookie($this->app->make(CartCookie::class)->name(), $token);

        $courier = function (int $town) use ($step): array {
            $methods = $step()->getJson("/api/delivery/methods?locality={$town}")->assertOk()->json('methods');
            $this->assertIsArray($methods);

            foreach ($methods as $method) {
                if (is_array($method) && ($method['key'] ?? null) === 'novaposhta-courier') {
                    return $method;
                }
            }

            return [];
        };

        $this->assertFalse($courier($withStreets)['disabled'] ?? null);
        $this->assertTrue($courier($without)['disabled'] ?? null);
        $this->assertSame('Courier delivery is not available here, choose another method', $courier($without)['hint'] ?? null);

        // The street is chosen from the carrier's directory, by the beginning of its name.
        $found = $step()->getJson("/api/delivery/streets?method=novaposhta-courier&locality={$withStreets}&q=Прикладна 1")->assertOk()->json('options');
        $this->assertNotSame([], $found);
        $this->assertStringContainsString('Прикладна 1', (string) $found[0]['label']);
    }

    #[Test]
    public function a_read_only_key_runs_the_streets_by_hand_and_walks_a_sample(): void
    {
        $pass = function (array $config): StreetsPass {
            foreach ($config as $key => $value) {
                config()->set('webx-delivery-novaposhta.'.$key, $value);
            }

            $this->app->forgetInstance(StreetsPass::class);

            return $this->app->make(StreetsPass::class);
        };

        $this->assertEquals(new StreetsPass(NovaPoshtaCarrier::WEEKLY, 20, 20.0, null), $pass(['read_only' => false]));
        $this->assertEquals(new StreetsPass('manual', 20, 20.0, 20), $pass(['read_only' => true]));
        // Said outright, a read-only key walks every town and on a schedule of its own.
        $this->assertEquals(new StreetsPass('0 1 * * 1', 5, 3.0, null), $pass([
            'sync.streets_sample' => '0', 'sync.streets_cron' => '0 1 * * 1', 'sync.max_requests_per_pull' => 5, 'sync.pull_seconds' => 3,
        ]));

        // The carrier the base resolves has the schedule of its pass.
        $this->app->forgetInstance(NovaPoshtaCarrier::class);
        $streets = $this->app->make(NovaPoshtaCarrier::class)->directories()[2];
        $this->assertSame([Directory::Streets, '0 1 * * 1', 2000], [$streets->directory, $streets->cron, $streets->pageSize]);
    }
}
