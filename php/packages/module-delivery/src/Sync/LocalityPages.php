<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use Illuminate\Support\Facades\DB;
use WebxUi\Geography\GeographyException;
use WebxUi\Geography\Matcher;
use WebxUi\Geography\Matching\LocalityRecord;
use WebxUi\Geography\Matching\MatchResult;

/**
 * Writes a page of a carrier's localities (§7.3): the bindings stored so far in one query, the
 * matcher over the whole page, one `upsert` by `(carrier, external_id)`, and — when a binding
 * moved — one `UPDATE` that takes the carrier's points along to the new locality.
 *
 * A binding a person made or ignored is kept by the matcher (§4.3, step 1); a pending one is
 * matched again every run, so that a town added by hand since resolves it.
 */
final class LocalityPages
{
    private const string TABLE = 'delivery_carrier_localities';

    public function __construct(private readonly Matcher $matcher) {}

    /** @param  list<mixed>  $records */
    public function write(string $carrier, int $runId, array $records): PageResult
    {
        $page = $this->records($records);

        if ($page === []) {
            return new PageResult;
        }

        $stored = DB::table(self::TABLE)
            ->select(['id', 'external_id', 'locality_id', 'status', 'method', 'reason', 'candidates'])
            ->where('carrier', $carrier)
            ->whereIn('external_id', array_map('strval', array_keys($page)))
            ->get()
            ->keyBy(static fn (object $row): string => (string) $row->external_id);

        $current = [];

        foreach ($stored as $id => $row) {
            $candidates = json_decode((string) ($row->candidates ?? 'null'), true);
            $current[$id] = MatchResult::fromRow(
                (string) $row->status,
                $row->locality_id === null ? null : (int) $row->locality_id,
                $row->method === null ? null : (string) $row->method,
                $row->reason === null ? null : (string) $row->reason,
                is_array($candidates) ? $candidates : null,
            );
        }

        try {
            $results = $this->matcher->matchPage($carrier, array_values($page), $current);
        } catch (GeographyException $failure) {
            throw new InvalidRecord($failure->getMessage(), 0, $failure);
        }

        $now = now();
        $rows = [];
        $moved = [];
        $queued = 0;

        foreach ($page as $id => $record) {
            $result = $results[$id];
            $was = $stored->get((string) $id);

            if ($was !== null && ($was->locality_id === null ? null : (int) $was->locality_id) !== $result->localityId) {
                $moved[] = (int) $was->id;
            }

            if (! $result->isSettled()) {
                $queued++;
            }

            $rows[] = [
                'carrier' => $carrier,
                'external_id' => (string) $id,
                'locality_id' => $result->localityId,
                'status' => $result->status->value,
                'method' => $result->method?->value,
                'reason' => $result->reason?->value,
                'candidates' => $result->candidates === [] ? null : json_encode($result->toArray()['candidates'], JSON_THROW_ON_ERROR),
                'record' => json_encode($record->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'features' => json_encode(array_values(array_unique(array_filter($record->features, is_string(...)))), JSON_THROW_ON_ERROR),
                'seen_run_id' => $runId,
                'removed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table(self::TABLE)->upsert($rows, ['carrier', 'external_id'], [
            'locality_id', 'status', 'method', 'reason', 'candidates', 'record', 'features', 'seen_run_id',
            'removed_at', 'updated_at',
        ]);

        // A binding made later takes its points along (§5 `carrier_locality_id`).
        if ($moved !== []) {
            DB::table('delivery_points')
                ->where('carrier', $carrier)
                ->whereIn('carrier_locality_id', $moved)
                ->update(['locality_id' => DB::raw('(select l.locality_id from '.self::TABLE.' l where l.id = delivery_points.carrier_locality_id)')]);
        }

        return new PageResult(
            seen: count($page),
            created: count($page) - $stored->count(),
            updated: $stored->count(),
            queued: $queued,
        );
    }

    /**
     * The page's records by external id; a record the page repeats counts once, its last state.
     *
     * @param  list<mixed>  $records
     * @return array<array-key, LocalityRecord>
     */
    private function records(array $records): array
    {
        $page = [];

        foreach ($records as $index => $record) {
            if (! $record instanceof LocalityRecord) {
                throw new InvalidRecord("Record {$index} of a localities page is not a ".LocalityRecord::class.'.');
            }

            $id = $record->externalId;

            if (trim($id) === '' || strlen($id) > 64) {
                throw new InvalidRecord("Record {$index}: the external id is empty or longer than 64 characters.");
            }

            $page[$id] = $record;
        }

        return $page;
    }
}
