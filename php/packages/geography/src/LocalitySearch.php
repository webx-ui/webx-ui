<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Geography\Models\GeoLocality;

/**
 * Suggestions for the locality field (§4.5): a prefix of any name, alias or later word, the
 * heaviest places first. Not cached: the indexed query is cheaper than writing a cache entry
 * (§17), and the buyer types a new prefix with every letter.
 */
final class LocalitySearch
{
    /** @return list<LocalitySuggestion> */
    public function suggest(string $country, string $query, string $locale, int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        $key = Normalizer::key($query);

        $localities = $this->query($country, $key)
            ->with('region')
            // Both descending: the index on (country_code, weight) — with the key InnoDB keeps
            // in it — is read backwards, with no sort, for the empty query.
            ->orderByDesc('weight')
            ->orderByDesc('id')
            ->limit($limit)
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
     * The active localities of a country whose search rows start with the key; an empty key —
     * all of them, so that the heaviest come first ("popular places").
     *
     * The rows are reached through a semi-join on `(country_code, name_key)`: the range on that
     * index is the whole cost, and a locality with three matching rows still comes once.
     *
     * @return Builder<GeoLocality>
     */
    public function query(string $country, string $key): Builder
    {
        $query = GeoLocality::query()
            ->where('country_code', strtoupper($country))
            ->where('active', true);

        if ($key === '') {
            return $query;
        }

        return $query->whereIn('id', static function ($names) use ($country, $key): void {
            $names->select('locality_id')
                ->from('geo_locality_names')
                ->where('country_code', strtoupper($country))
                ->whereRaw("name_key like ? escape '!'", [Normalizer::likePrefix($key)]);
        });
    }
}
