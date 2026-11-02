<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use Illuminate\Support\Facades\DB;
use WebxUi\Geography\Names;
use WebxUi\Geography\Normalizer;
use WebxUi\Localization\Locales;

/**
 * Writes a page of a carrier's streets (§7.3, §8.1): the carrier's localities of the page in one
 * `whereIn`, which of the streets are already there in another, and one `upsert` by
 * `(carrier_locality_id, external_id)` — four statements for a page of 2 000 with the journal.
 *
 * A street of a locality the carrier never gave is skipped and counted, as a point is. A street
 * of a locality not bound yet is written all the same: it hangs on the carrier's locality, and
 * the binding made later brings it along with nothing to move.
 */
final class StreetPages
{
    public const string UNKNOWN_LOCALITY = PointPages::UNKNOWN_LOCALITY;

    private const string TABLE = 'delivery_streets';

    public function __construct(private readonly Locales $locales) {}

    /** @param  list<mixed>  $records */
    public function write(string $carrier, int $runId, array $records): PageResult
    {
        $page = $this->records($records);

        if ($page === []) {
            return new PageResult;
        }

        $localities = DB::table('delivery_carrier_localities')
            ->where('carrier', $carrier)
            ->whereIn('external_id', array_values(array_unique(array_map(static fn (StreetRecord $record): string => $record->localityExternalId, $page))))
            ->pluck('id', 'external_id');

        $existing = $this->existing($carrier, $localities->all(), $page);

        $default = $this->locales->defaultCode();
        $now = now();
        $rows = [];
        $skipped = [];
        $updated = 0;

        foreach ($page as $record) {
            $locality = $localities->get($record->localityExternalId);

            if ($locality === null) {
                $skipped[] = $record->externalId;

                continue;
            }

            $names = Names::clean($record->names);
            $name = trim((string) preg_replace('/\s+/u', ' ', $record->name));

            if ($name !== '' && ! isset($names[$default])) {
                $names = [$default => mb_substr($name, 0, Normalizer::MAX_LENGTH, 'UTF-8'), ...$names];
            }

            $updated += isset($existing[$locality.':'.$record->externalId]) ? 1 : 0;

            $rows[] = [
                'carrier' => $carrier,
                'carrier_locality_id' => (int) $locality,
                'external_id' => $record->externalId,
                'kind' => in_array($record->kind, StreetRecord::KINDS, true) ? $record->kind : 'other',
                'names' => json_encode($names, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'name_key' => Normalizer::key((string) Names::main($names)),
                'seen_run_id' => $runId,
                'removed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table(self::TABLE)->upsert($rows, ['carrier_locality_id', 'external_id'], [
                'kind', 'names', 'name_key', 'seen_run_id', 'removed_at', 'updated_at',
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
     * Which streets of the page are there already, by `carrier_locality_id:external_id`: one `IN`
     * by the carrier's street ids over `(carrier, external_id)`, the pairs told apart here. Two
     * lists `IN` each other would make the database try every pair against the unique index —
     * millions of probes for a page of 2 000 streets spread over as many localities.
     *
     * @param  array<string, int|string>  $localities  the carrier's locality id → row id
     * @param  array<string, StreetRecord>  $page
     * @return array<string, true>
     */
    private function existing(string $carrier, array $localities, array $page): array
    {
        $wanted = [];

        foreach ($page as $record) {
            if (isset($localities[$record->localityExternalId])) {
                $wanted[$localities[$record->localityExternalId].':'.$record->externalId] = $record->externalId;
            }
        }

        if ($wanted === []) {
            return [];
        }

        $existing = [];
        $rows = DB::table(self::TABLE)
            ->select(['carrier_locality_id', 'external_id'])
            ->where('carrier', $carrier)
            ->whereIn('external_id', array_values(array_unique($wanted)))
            ->get();

        foreach ($rows as $row) {
            $key = $row->carrier_locality_id.':'.$row->external_id;

            if (isset($wanted[$key])) {
                $existing[$key] = true;
            }
        }

        return $existing;
    }

    /**
     * Keyed by the carrier's locality and the street's id: the same street twice on a page is
     * written once, as the unique key would have it.
     *
     * @param  list<mixed>  $records
     * @return array<string, StreetRecord>
     */
    private function records(array $records): array
    {
        $page = [];

        foreach ($records as $index => $record) {
            if (! $record instanceof StreetRecord) {
                throw new InvalidRecord("Record {$index} of a streets page is not a ".StreetRecord::class.'.');
            }

            $id = $record->externalId;

            if (trim($id) === '' || strlen($id) > 64) {
                throw new InvalidRecord("Record {$index}: the external id is empty or longer than 64 characters.");
            }

            if (trim($record->localityExternalId) === '') {
                throw new InvalidRecord("Street {$id}: no locality.");
            }

            if (trim($record->name) === '' && Names::clean($record->names) === []) {
                throw new InvalidRecord("Street {$id}: no name.");
            }

            $page[$record->localityExternalId."\0".$id] = $record;
        }

        return $page;
    }
}
