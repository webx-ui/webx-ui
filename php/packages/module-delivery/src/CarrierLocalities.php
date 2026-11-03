<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Support\Facades\DB;

/**
 * A carrier's own localities as the base keeps them (`delivery_carrier_localities`), read by a
 * satellite without touching the base's model: the ids to walk for its streets (§6.4 of the
 * satellite's spec) and the id of a shared locality at the carrier, which a waybill names.
 *
 * Reading only: a satellite never writes into the base.
 */
final class CarrierLocalities
{
    /**
     * The carrier's ids of its localities not removed, in ascending order after `$after` — a page
     * of a keyset walk on the unique index (carrier, external_id).
     *
     * @param  string|null  $feature  only the localities with this feature (`courier`)
     * @return list<string>
     */
    public function externalIds(string $carrier, ?string $after = null, int $limit = 200, ?string $feature = null): array
    {
        $query = DB::table('delivery_carrier_localities')
            ->where('carrier', $carrier)
            ->whereNull('removed_at');

        if ($after !== null) {
            $query->where('external_id', '>', $after);
        }

        if ($feature !== null) {
            // `features` is a JSON list of words; the same match the sync counts couriers with.
            $query->where('features', 'like', '%'.json_encode($feature).'%');
        }

        return $query->orderBy('external_id')
            ->limit(max(1, $limit))
            ->pluck('external_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /** The carrier's id of a shared locality — the one its binding stands on — or null. */
    public function externalId(string $carrier, int $locality): ?string
    {
        $id = DB::table('delivery_carrier_localities')
            ->where('carrier', $carrier)
            ->where('locality_id', $locality)
            ->whereNull('removed_at')
            ->orderBy('id')
            ->value('external_id');

        return $id === null ? null : (string) $id;
    }

    /**
     * The shared locality a carrier's id stands on, or null — not bound, removed or unknown.
     */
    public function localityId(string $carrier, string $externalId): ?int
    {
        $id = DB::table('delivery_carrier_localities')
            ->where('carrier', $carrier)
            ->where('external_id', $externalId)
            ->whereNull('removed_at')
            ->value('locality_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * The carrier's ids of these shared localities, one query: locality id → the carrier's id.
     * A locality the carrier does not reach is left out; of two records on one, the first.
     *
     * @param  list<int>  $localities
     * @return array<int, string>
     */
    public function forLocalities(string $carrier, array $localities): array
    {
        if ($localities === []) {
            return [];
        }

        $ids = [];

        DB::table('delivery_carrier_localities')
            ->where('carrier', $carrier)
            ->whereIn('locality_id', $localities)
            ->whereNull('removed_at')
            ->orderBy('id')
            ->get(['locality_id', 'external_id'])
            ->each(static function (object $row) use (&$ids): void {
                $ids[(int) $row->locality_id] ??= (string) $row->external_id;
            });

        return $ids;
    }
}
