<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use Illuminate\Support\Facades\DB;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Geography\Names;
use WebxUi\Localization\Locales;

/**
 * Writes a page of a carrier's points (§7.3): the carrier's localities of the page in one
 * `whereIn`, which of the points are already there in another, and one `upsert` by
 * `(carrier, external_id)`.
 *
 * A point of a locality that is not bound yet is written with `locality_id = null`: the checkout
 * does not see it until the binding is made, and then it follows by `carrier_locality_id`. A
 * point of a locality the carrier never gave is skipped and counted — the carrier's directory is
 * wrong, and the run says so in its warnings.
 */
final class PointPages
{
    public const string UNKNOWN_LOCALITY = 'unknown_locality';

    private const string TABLE = 'delivery_points';

    public function __construct(private readonly Locales $locales) {}

    /** @param  list<mixed>  $records */
    public function write(string $carrier, int $runId, array $records): PageResult
    {
        $page = $this->records($records);

        if ($page === []) {
            return new PageResult;
        }

        $localities = DB::table('delivery_carrier_localities')
            ->select(['id', 'external_id', 'locality_id', 'status'])
            ->where('carrier', $carrier)
            ->whereIn('external_id', array_values(array_unique(array_map(static fn (PointRecord $record): string => $record->localityExternalId, $page))))
            ->get()
            ->keyBy(static fn (object $row): string => (string) $row->external_id);

        $existing = DB::table(self::TABLE)
            ->where('carrier', $carrier)
            ->whereIn('external_id', array_map('strval', array_keys($page)))
            ->pluck('external_id')
            ->flip();

        $default = $this->locales->defaultCode();
        $now = now();
        $rows = [];
        $skipped = [];
        $updated = 0;

        foreach ($page as $id => $record) {
            $locality = $localities->get($record->localityExternalId);

            if ($locality === null) {
                $skipped[] = (string) $id;

                continue;
            }

            $titles = Names::clean($record->titles);
            $title = trim((string) $record->title);

            if ($title !== '' && ! in_array($title, $titles, true) && ! isset($titles[$default])) {
                $titles = [$default => $title, ...$titles];
            }

            $address = Names::clean($record->addresses);
            $line = trim($record->address);

            if ($line !== '' && ! isset($address[$default])) {
                $address = [$default => $line, ...$address];
            }

            $number = self::cut($record->number, 16);
            $updated += $existing->has((string) $id) ? 1 : 0;

            $rows[] = [
                'carrier' => $carrier,
                'external_id' => (string) $id,
                'kind' => $record->kind,
                'locality_id' => $locality->status === 'matched' && $locality->locality_id !== null ? (int) $locality->locality_id : null,
                'carrier_locality_id' => (int) $locality->id,
                'number' => $number,
                'titles' => $titles === [] ? null : json_encode($titles, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'address' => json_encode($address, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'postcode' => self::cut($record->postcode, 16),
                'lat' => self::coordinate($record->lat, 90),
                'lng' => self::coordinate($record->lng, 180),
                'schedule' => $record->schedule === null ? null : json_encode($record->schedule->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'limits' => $record->limits === null ? null : json_encode($record->limits->toArray(), JSON_THROW_ON_ERROR),
                'phone' => self::cut($record->phone, 32),
                'search' => DeliveryPoint::searchOf($number, $titles, $address),
                'meta' => $record->meta === [] ? null : json_encode($record->meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'active' => $record->active,
                'restriction' => $record->restriction,
                'seen_run_id' => $runId,
                'removed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table(self::TABLE)->upsert($rows, ['carrier', 'external_id'], [
                'kind', 'locality_id', 'carrier_locality_id', 'number', 'titles', 'address', 'postcode', 'lat',
                'lng', 'schedule', 'limits', 'phone', 'search', 'meta', 'active', 'restriction', 'seen_run_id', 'removed_at',
                'updated_at',
            ]);
        }

        return new PageResult(
            seen: count($page),
            created: count($rows) - $updated,
            updated: $updated,
            skipped: $skipped === [] ? [] : [self::UNKNOWN_LOCALITY => $skipped],
        );
    }

    /**
     * @param  list<mixed>  $records
     * @return array<array-key, PointRecord>
     */
    private function records(array $records): array
    {
        $page = [];

        foreach ($records as $index => $record) {
            if (! $record instanceof PointRecord) {
                throw new InvalidRecord("Record {$index} of a points page is not a ".PointRecord::class.'.');
            }

            $id = $record->externalId;

            if (trim($id) === '' || strlen($id) > 64) {
                throw new InvalidRecord("Record {$index}: the external id is empty or longer than 64 characters.");
            }

            if (preg_match('/^[a-z0-9_-]{1,32}$/', $record->kind) !== 1) {
                throw new InvalidRecord("Point {$id}: [{$record->kind}] is not a kind of point — a-z, 0-9, \"_\" and \"-\", at most 32.");
            }

            if ($record->restriction !== null && preg_match('/^[a-z0-9_-]{1,32}$/', $record->restriction) !== 1) {
                throw new InvalidRecord("Point {$id}: [{$record->restriction}] is not a restriction — a-z, 0-9, \"_\" and \"-\", at most 32.");
            }

            if (trim($record->localityExternalId) === '') {
                throw new InvalidRecord("Point {$id}: no locality.");
            }

            if (trim($record->address) === '' && Names::clean($record->addresses) === []) {
                throw new InvalidRecord("Point {$id}: no address.");
            }

            $page[$id] = $record;
        }

        return $page;
    }

    private static function cut(?string $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length, 'UTF-8');
    }

    private static function coordinate(?float $value, int $limit): ?float
    {
        return $value === null || ! is_finite($value) || abs($value) > $limit ? null : round($value, 6);
    }
}
