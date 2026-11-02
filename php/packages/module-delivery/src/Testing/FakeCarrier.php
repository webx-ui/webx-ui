<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Testing;

use LogicException;
use WebxUi\Delivery\ConnectionResult;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\SyncsDirectories;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\DirectorySchedule;
use WebxUi\Delivery\Sync\Limits;
use WebxUi\Delivery\Sync\Page;
use WebxUi\Delivery\Sync\PointRecord;
use WebxUi\Delivery\Sync\Schedule;
use WebxUi\Delivery\Sync\StreetRecord;
use WebxUi\Geography\Matching\LocalityRecord;

/**
 * «Carrier A» (`carrier-a`) for tests and the playground (§18): a deterministic directory of the
 * size asked for, in `XX` — the private-use code, no real country.
 *
 * Localities `L0…` are «Town N» in «Region A»…«Region E», every tenth a city, every seventh
 * spelled with a typographic apostrophe («O’Town N»), every other one with a courier; and two
 * namesake villages «Village Same» in Region A and Region B. Points `P0…` are branches and every
 * third a locker, spread over the localities in turn, at «N Example street», phone
 * `+800 1234 5678`. Streets `S0…` (only when asked for: a carrier without a courier declares no
 * streets directory) go to the localities with a courier in turn — all but `L0`, the city where
 * the carrier says it goes to doors and gives no street (§8.2); every eighth is spelled with a
 * typographic apostrophe («O’Neil 7»).
 *
 * What a test needs to go wrong goes wrong on request: a page that fails, a directory that comes
 * back empty, records that vanish, and a lock that makes any `pull()` outside a sync throw — the
 * checkout never calls the carrier for its directories (decision 38).
 *
 * Registered in tests and in the playground; on a development site only by
 * `WEBX_DELIVERY_FAKE_CARRIER=true` ({@see FakeCarrierSite}), never in production.
 */
final class FakeCarrier implements Carrier, SyncsDirectories
{
    public const string KEY = 'carrier-a';

    /** @var list<array{directory: string, cursor: string|null}> every pull, in order */
    public array $pulls = [];

    /** @var array<string, array<int, int>> directory → page number → failures left */
    private array $failures = [];

    /** @var array<string, true> */
    private array $empty = [];

    /** @var array<string, array<int, true>> directory → indexes of records the carrier no longer has */
    private array $dropped = [];

    private bool $locked = false;

    public function __construct(
        public int $localities = 50,
        public int $points = 200,
        public int $pageSize = 500,
        public bool $namesakes = true,
        public int $streets = 0,
        public int $streetPageSize = 2000,
    ) {}

    /** The size of the directories and of a page. */
    public function size(?int $localities = null, ?int $points = null, ?int $pageSize = null, ?int $streets = null, ?int $streetPageSize = null): self
    {
        $this->localities = $localities ?? $this->localities;
        $this->points = $points ?? $this->points;
        $this->pageSize = $pageSize ?? $this->pageSize;
        $this->streets = $streets ?? $this->streets;
        $this->streetPageSize = $streetPageSize ?? $this->streetPageSize;

        return $this;
    }

    /** Page `$page` (from 1) of the directory throws `CarrierUnavailable` `$times` times. */
    public function failOnPage(Directory $directory, int $page, int $times = PHP_INT_MAX): self
    {
        $this->failures[$directory->value][$page] = $times;

        return $this;
    }

    /** The directory comes back with no records at all — the cut-off answer a threshold is for. */
    public function returnNothing(Directory $directory, bool $empty = true): self
    {
        if ($empty) {
            $this->empty[$directory->value] = true;
        } else {
            unset($this->empty[$directory->value]);
        }

        return $this;
    }

    /**
     * The carrier no longer has these records (by index: 0 is `L0` or `P0`); pages keep their
     * bounds, so what follows does not move.
     *
     * @param  list<int>  $indexes
     */
    public function drop(Directory $directory, array $indexes): self
    {
        $this->dropped[$directory->value] = array_fill_keys($indexes, true);

        return $this;
    }

    /** From now on any pull throws: what a checkout request must never reach. */
    public function lock(bool $locked = true): self
    {
        $this->locked = $locked;

        return $this;
    }

    // Carrier

    public function key(): string
    {
        return self::KEY;
    }

    public function title(string $locale): string
    {
        return 'Carrier A';
    }

    public function icon(): ?string
    {
        return null;
    }

    public function countries(): array
    {
        return ['XX'];
    }

    public function settings(): array
    {
        return [];
    }

    public function credentials(): array
    {
        return [['name' => 'api_key', 'type' => 'secret']];
    }

    public function testConnection(array $credentials): ConnectionResult
    {
        return ConnectionResult::ok();
    }

    // SyncsDirectories

    public function directories(): array
    {
        $directories = [
            new DirectorySchedule(Directory::Localities, '30 3 * * *', $this->pageSize),
            new DirectorySchedule(Directory::Points, '30 3 * * *', $this->pageSize),
        ];

        if ($this->streets > 0) {
            // Streets change least and take longest: once a week (§7.1).
            $directories[] = new DirectorySchedule(Directory::Streets, '0 4 * * 0', $this->streetPageSize);
        }

        return $directories;
    }

    public function pull(Directory $directory, ?string $cursor): Page
    {
        if ($this->locked) {
            throw new LogicException('The carrier\'s directory was pulled outside a sync.');
        }

        $this->pulls[] = ['directory' => $directory->value, 'cursor' => $cursor];
        $size = max(1, $directory === Directory::Streets ? $this->streetPageSize : $this->pageSize);
        $offset = max(0, (int) $cursor);
        $number = intdiv($offset, $size) + 1;

        if (($this->failures[$directory->value][$number] ?? 0) > 0) {
            $this->failures[$directory->value][$number]--;

            throw new CarrierUnavailable("Carrier A: HTTP 503 on page {$number} of {$directory->value}.");
        }

        if (isset($this->empty[$directory->value])) {
            return new Page([], null);
        }

        $total = $this->total($directory);
        $records = [];

        for ($index = $offset; $index < min($total, $offset + $size); $index++) {
            if (! isset($this->dropped[$directory->value][$index])) {
                $records[] = match ($directory) {
                    Directory::Localities => $this->locality($index),
                    Directory::Points => $this->point($index),
                    Directory::Streets => $this->street($index),
                };
            }
        }

        $next = $offset + $size;

        return new Page($records, $next < $total ? (string) $next : null);
    }

    public function total(Directory $directory): int
    {
        return match ($directory) {
            Directory::Localities => $this->localities + ($this->namesakes ? 2 : 0),
            Directory::Points => $this->points,
            Directory::Streets => $this->streets,
        };
    }

    public function locality(int $index): LocalityRecord
    {
        if ($index >= $this->localities) {
            // The namesakes: one village name in two regions, told apart by the region.
            $second = $index > $this->localities;

            return new LocalityRecord(
                externalId: 'L'.$index,
                country: 'XX',
                name: 'Village Same',
                kind: 'village',
                region: $second ? 'Region B' : 'Region A',
                lat: $second ? 10.5 : 10.1,
                lng: $second ? 20.5 : 20.1,
            );
        }

        $name = ($index % 7 === 3 ? 'O’Town ' : 'Town ').$index;

        return new LocalityRecord(
            externalId: 'L'.$index,
            country: 'XX',
            name: $name,
            kind: $index % 10 === 0 ? 'city' : 'town',
            region: 'Region '.chr(65 + $index % 5),
            lat: 10 + intdiv($index, 100) * 0.1,
            lng: 20 + ($index % 100) * 0.1,
            names: ['en' => $name],
            features: $index % 2 === 0 ? ['courier'] : [],
        );
    }

    public function point(int $index): PointRecord
    {
        $locker = $index % 3 === 0;

        return new PointRecord(
            externalId: 'P'.$index,
            kind: $locker ? 'locker' : 'branch',
            localityExternalId: 'L'.($index % max(1, $this->localities)),
            address: $index.' Example street',
            number: (string) $index,
            title: ($locker ? 'Locker ' : 'Branch ').$index,
            postcode: sprintf('%05d', $index % 100000),
            lat: 10 + intdiv($index, 1000) * 0.01,
            lng: 20 + ($index % 1000) * 0.01,
            schedule: $locker
                ? new Schedule(['mon' => [['00:00', '24:00']], 'tue' => [['00:00', '24:00']], 'wed' => [['00:00', '24:00']], 'thu' => [['00:00', '24:00']], 'fri' => [['00:00', '24:00']], 'sat' => [['00:00', '24:00']], 'sun' => [['00:00', '24:00']]])
                : new Schedule(['mon' => [['09:00', '18:00']], 'tue' => [['09:00', '18:00']], 'wed' => [['09:00', '18:00']], 'thu' => [['09:00', '18:00']], 'fri' => [['09:00', '18:00']], 'sat' => [], 'sun' => null]),
            limits: new Limits(maxWeightG: $locker ? 20000 : 30000, cod: ! $locker),
            phone: '+800 1234 5678',
        );
    }

    /** The locality index of street `$index`: the localities with a courier but `L0`, in turn. */
    public function streetLocality(int $index): int
    {
        $couriers = max(1, intdiv($this->localities - 1, 2));

        return 2 * (1 + $index % $couriers);
    }

    public function street(int $index): StreetRecord
    {
        $words = ['Example', 'Sample', 'Oak', 'Maple', 'River', 'Hill', 'Garden', 'O’Neil'];
        $kinds = ['street', 'street', 'street', 'street', 'street', 'street', 'street', 'avenue', 'lane', 'square'];
        $name = $words[$index % 8].' '.$index;

        return new StreetRecord(
            externalId: 'S'.$index,
            localityExternalId: 'L'.$this->streetLocality($index),
            name: $name,
            kind: $kinds[$index % 10],
            names: ['en' => $name],
        );
    }
}
