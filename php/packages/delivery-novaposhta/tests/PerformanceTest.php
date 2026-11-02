<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;

/**
 * A page of 500 towns and of 500 points through the base's writing (§12): the number of queries
 * does not grow with the page or its place in the run, and the carrier is asked one reading request
 * a page.
 */
final class PerformanceTest extends TestCase
{
    private const int TOWNS = 1000;

    private const array AREAS = ['Полтавська', 'Київська', 'Львівська', 'Одеська', 'Харківська', 'Волинська'];

    protected function setUp(): void
    {
        parent::setUp();

        DeliveryCarrier::query()->create(['key' => NovaPoshtaCarrier::KEY, 'enabled' => true, 'credentials' => ['api_key' => self::KEY]]);

        Http::fake([self::URL => static function (Request $request) {
            $page = (int) ($request['methodProperties']['Page'] ?? 1);
            $limit = (int) ($request['methodProperties']['Limit'] ?? 500);
            $entries = [];
            $points = $request['calledMethod'] === 'getWarehouses';

            for ($n = ($page - 1) * $limit; $n < min($page * $limit, self::TOWNS); $n++) {
                $entries[] = $points ? self::point($n) : self::town($n);
            }

            return Http::response(self::answer($entries, total: self::TOWNS));
        }]);
    }

    /** @return array<string, mixed> */
    private static function town(int $n): array
    {
        $area = self::AREAS[$n % count(self::AREAS)];

        return [
            'Description' => "Приклад {$n}",
            'DescriptionRu' => "Пример {$n}",
            'Ref' => sprintf('00000000-0000-4000-8000-%012d', $n),
            'Delivery1' => $n % 2 === 0 ? '1' : '0', 'Delivery2' => '0', 'Delivery3' => '0', 'Delivery4' => '0',
            'Delivery5' => '0', 'Delivery6' => '0', 'Delivery7' => '0',
            'Area' => sprintf('00000000-0000-4000-9000-%012d', $n % count(self::AREAS)),
            'CityID' => (string) $n,
            'SettlementTypeDescription' => $n % 10 === 0 ? 'місто' : 'село',
            'AreaDescription' => $area,
            'AreaDescriptionRu' => $area,
        ];
    }

    /**
     * A point of town `$n`: a branch, a locker, or a locker for the residents of a block in turn.
     *
     * @return array<string, mixed>
     */
    private static function point(int $n): array
    {
        $days = array_fill_keys(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], $n % 3 === 0 ? '08:00-19:00' : '00:01-23:59');

        return [
            'Ref' => sprintf('00000000-0000-4000-a000-%012d', $n),
            'Number' => (string) ($n + 1),
            'Description' => "Відділення №{$n}: вул. Прикладна, {$n}",
            'DescriptionRu' => "Отделение №{$n}: ул. Примерная, {$n}",
            'ShortAddress' => "Приклад {$n}, Прикладна, {$n}",
            'ShortAddressRu' => "Пример {$n}, Примерная, {$n}",
            'CityRef' => sprintf('00000000-0000-4000-8000-%012d', $n),
            'CategoryOfWarehouse' => $n % 3 === 0 ? 'Branch' : 'Postomat',
            'PostMachineType' => $n % 3 === 2 ? 'ForResidentsOfEntrance' : '',
            'DenyToSelect' => '0',
            'WarehouseStatus' => 'Working',
            'PostalCodeUA' => '01001',
            'Latitude' => (string) (48 + $n / 1000),
            'Longitude' => (string) (30 + $n / 1000),
            'Schedule' => $days,
            'PlaceMaxWeightAllowed' => '0',
            'TotalMaxWeightAllowed' => $n % 3 === 0 ? '30' : '20',
            'ReceivingLimitationsOnDimensions' => ['Width' => 40, 'Height' => 30, 'Length' => 60],
        ];
    }

    /**
     * The queries of every page of one pass of a directory.
     *
     * @return list<int>
     */
    private function pass(Directory $directory = Directory::Localities): array
    {
        $sync = $this->app->make(Synchronizer::class);
        $run = $sync->start(NovaPoshtaCarrier::KEY, $directory, Trigger::Command);
        $costs = [];

        do {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $more = $sync->page($run);
            $costs[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        } while ($more);

        $this->assertSame(RunStatus::Succeeded, $run->refresh()->status, (string) $run->error);
        $this->assertSame([3, self::TOWNS], [$run->pages, $run->seen]);

        return $costs;
    }

    #[Test]
    public function a_page_of_500_known_towns_costs_the_same_few_queries(): void
    {
        $this->pass();
        $costs = $this->pass();

        // Two full pages and the empty one that ends the pass with its counts and the journal.
        [$first, $second] = $costs;
        $this->assertSame($first, $second, json_encode($costs, JSON_THROW_ON_ERROR));
        $this->assertLessThanOrEqual(8, $first, 'a page of 500 towns');
        $this->assertLessThanOrEqual($first + 8, end($costs));

        // One reading request a page, nothing else.
        Http::assertSentCount(6);
        Http::assertSent(static fn (Request $request): bool => $request['calledMethod'] === 'getCities');
        $this->assertSame(['getCities'], array_values(array_unique(array_column($this->journalEntries(), 'method'))));
    }

    #[Test]
    public function a_page_of_500_points_costs_the_same_few_queries(): void
    {
        $this->pass();
        $this->pass(Directory::Points);
        $costs = $this->pass(Directory::Points);

        // Two full pages and the empty one that ends the pass with the removals, the weights of
        // the towns, where they lie, and the journal.
        [$first, $second] = $costs;
        $this->assertSame($first, $second, json_encode($costs, JSON_THROW_ON_ERROR));
        $this->assertLessThanOrEqual(8, $first, 'a page of 500 points');
        $this->assertLessThanOrEqual($first + 10, end($costs));
        $this->assertSame(self::TOWNS, DB::table('delivery_points')->where('carrier', NovaPoshtaCarrier::KEY)->whereNotNull('locality_id')->count());
        $this->assertSame(intdiv(self::TOWNS, 3), DB::table('delivery_points')->where('restriction', 'residents')->count());

        // One reading request a page: three of the towns, two passes of three of the points.
        Http::assertSentCount(9);
        $this->assertSame(['getCities', 'getWarehouses'], array_values(array_unique(array_column($this->journalEntries(), 'method'))));
    }
}
