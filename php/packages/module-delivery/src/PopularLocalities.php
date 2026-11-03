<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Support\Facades\DB;
use WebxUi\Delivery\Models\DeliveryPopularLocality;
use WebxUi\Geography\LocalitySuggestion;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Names;

/**
 * The popular towns of «Where to deliver?» (decision 40): one list for the whole site, which the
 * administrator picks from the shared geography and orders by dragging. An empty list — no
 * buttons, only the search.
 *
 * A locality that is deleted leaves the list by its foreign key; one that is switched off stays
 * in it and is not shown until it is on again, so the administrator's order survives.
 */
final class PopularLocalities
{
    /**
     * The ids in their order, the switched-off ones included — what the panel edits.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return DeliveryPopularLocality::query()
            ->orderBy('position')
            ->orderBy('locality_id')
            ->pluck('locality_id')
            ->map(intval(...))
            ->values()
            ->all();
    }

    /**
     * The buttons of the dialog: active localities of the country in the list's order, named in
     * this language. Two queries: the list joined to its localities, and their regions.
     *
     * @return list<LocalitySuggestion>
     */
    public function suggestions(string $country, string $locale): array
    {
        $localities = GeoLocality::query()
            ->select('geo_localities.*')
            ->join('delivery_popular_localities as popular', 'popular.locality_id', '=', 'geo_localities.id')
            ->where('geo_localities.country_code', strtoupper($country))
            ->where('geo_localities.active', true)
            ->orderBy('popular.position')
            ->orderBy('geo_localities.id')
            ->with('region')
            ->get();

        return $localities
            ->map(static fn (GeoLocality $locality): LocalitySuggestion => new LocalitySuggestion(
                id: $locality->id,
                name: $locality->name($locale),
                kind: $locality->kind,
                district: Names::pick($locality->district, $locale),
                region: $locality->region?->name($locale),
                label: $locality->label($locale),
            ))
            ->values()
            ->all();
    }

    /**
     * Replaces the whole list with these localities, in this order; a repeated id counts once.
     *
     * @param  list<int>  $ids
     * @return list<int> the list as it is now
     *
     * @throws DeliveryException an id that is no locality — nothing is changed
     */
    public function set(array $ids): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        $this->assertLocalities($ids);

        DB::transaction(static function () use ($ids): void {
            DeliveryPopularLocality::query()->whereNotIn('locality_id', $ids === [] ? [0] : $ids)->delete();

            $now = now();
            $rows = [];

            foreach ($ids as $position => $id) {
                $rows[] = ['locality_id' => $id, 'position' => $position, 'created_at' => $now, 'updated_at' => $now];
            }

            if ($rows !== []) {
                DeliveryPopularLocality::query()->upsert($rows, ['locality_id'], ['position', 'updated_at']);
            }
        });

        return $this->ids();
    }

    /**
     * Adds a locality at a position (counted from 0), the end by default; one already in the list moves there.
     *
     * @return list<int>
     */
    public function add(int $id, ?int $position = null): array
    {
        $ids = array_values(array_filter($this->ids(), static fn (int $other): bool => $other !== $id));
        $position = $position === null ? count($ids) : max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$id]);

        return $this->set($ids);
    }

    /** @return list<int> */
    public function remove(int $id): array
    {
        DeliveryPopularLocality::query()->whereKey($id)->delete();

        return $this->ids();
    }

    /** @param  list<int>  $ids */
    private function assertLocalities(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $found = GeoLocality::query()->whereKey($ids)->pluck('id')->map(intval(...))->all();
        $missing = array_values(array_diff($ids, $found));

        if ($missing !== []) {
            throw new DeliveryException('No locality with id '.implode(', ', $missing).': the popular towns are not changed.');
        }
    }
}
