<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoLocalityName;

/**
 * Keeps `geo_locality_names` — the rows suggestions are searched in — in step with a locality.
 *
 * The names in the site's languages are rewritten from the record; a carrier's spelling
 * (`alias`) stays, it is not the record's to drop. Every name of several words gets a `word`
 * row per tail, carrying the language and source of the name it came from — a tail of a name has
 * a language, a tail of an alias has none, and that is how a rewrite tells them apart.
 */
final class NameIndex
{
    public static function sync(GeoLocality $locality): void
    {
        GeoLocalityName::query()
            ->where('locality_id', $locality->id)
            ->where(static function ($query): void {
                $query->where('kind', GeoLocalityName::NAME)
                    ->orWhere(static fn ($words) => $words->where('kind', GeoLocalityName::WORD)->whereNotNull('locale'));
            })
            ->delete();

        // An alias stays, but it moves with the locality: the index starts with the country.
        GeoLocalityName::query()
            ->where('locality_id', $locality->id)
            ->where('country_code', '!=', $locality->country_code)
            ->update(['country_code' => $locality->country_code]);

        $rows = [];

        foreach (Names::clean($locality->names) as $locale => $name) {
            $rows = [...$rows, ...self::rows($locality, $name, GeoLocalityName::NAME, $locale, null)];
        }

        self::insert($rows);
    }

    /**
     * A spelling of the locality that is not one of its names — a carrier's "t. Town D" for
     * "Town D" — so that the next record spelled that way finds it (§4.3).
     */
    public static function addAlias(GeoLocality $locality, string $name, ?string $source = null): void
    {
        $name = trim($name);

        if ($name === '' || Normalizer::key($name) === '') {
            return;
        }

        self::insert(self::rows($locality, $name, GeoLocalityName::ALIAS, null, $source));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(GeoLocality $locality, string $name, string $kind, ?string $locale, ?string $source): array
    {
        $key = Normalizer::key($name);

        if ($key === '') {
            return [];
        }

        $base = [
            'locality_id' => $locality->id,
            'country_code' => $locality->country_code,
            'locale' => $locale,
            'source' => $source,
        ];

        $rows = [[...$base, 'name' => mb_substr($name, 0, Normalizer::MAX_LENGTH, 'UTF-8'), 'name_key' => $key, 'kind' => $kind]];

        foreach (Normalizer::tails($key) as $tail) {
            $rows[] = [...$base, 'name' => mb_substr($name, 0, Normalizer::MAX_LENGTH, 'UTF-8'), 'name_key' => $tail, 'kind' => GeoLocalityName::WORD];
        }

        return $rows;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private static function insert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        // Two languages spelling the name alike are one row: `(locality_id, name_key)` is unique.
        GeoLocalityName::query()->insertOrIgnore($rows);
    }
}
