<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use Illuminate\Support\Facades\DB;
use WebxUi\Geography\Matching\CarrierBindings;

/**
 * The localities a carrier's records are bound to, from `delivery_carrier_localities` (§4.3): one
 * query a page, on the `(carrier, locality_id)` index. A record no longer listed (`removed_at`)
 * holds its town no more.
 */
final class StoredCarrierBindings implements CarrierBindings
{
    public function taken(string $carrier, array $localityIds, array $except): array
    {
        if ($localityIds === []) {
            return [];
        }

        return DB::table('delivery_carrier_localities')
            ->where('carrier', $carrier)
            ->whereIn('locality_id', $localityIds)
            ->whereNull('removed_at')
            ->when($except !== [], static fn ($query) => $query->whereNotIn('external_id', $except))
            ->distinct()
            ->pluck('locality_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
