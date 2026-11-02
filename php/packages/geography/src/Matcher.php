<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use WebxUi\Geography\Matching\BindingMethod;
use WebxUi\Geography\Matching\Candidate;
use WebxUi\Geography\Matching\CarrierBindings;
use WebxUi\Geography\Matching\LeadingCarriers;
use WebxUi\Geography\Matching\LocalityRecord;
use WebxUi\Geography\Matching\MatchResult;
use WebxUi\Geography\Matching\QueueReason;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoLocalityName;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Geography\Models\GeoRegionAlias;
use WebxUi\Localization\Locales;

/**
 * Binds a carrier's localities to the shared geography (§4.3–§4.4 of the delivery spec).
 *
 * In order, up to the first answer:
 *
 * 1. a binding that is already settled — bound to a locality, or ignored by a person — is kept;
 * 2. the region is found by its key or an alias within the country; unknown — the queue
 *    (`region_unknown`), unless the carrier leads the country, which brings the region in;
 * 3. one locality of the same country, region, type and name (or alias), district agreeing —
 *    bound (`auto_exact`);
 * 4. several namesakes — the one within the radius, if it is the only one (`auto_coords`),
 *    otherwise the queue (`ambiguous`) with the candidates;
 * 5. only a locality of another type — bound if within the radius, otherwise the queue
 *    (`kind_mismatch`);
 * 6. nothing — the leading carrier makes the shared locality (`created`), any other goes to the
 *    queue (`not_found`).
 *
 * Every locality is a candidate, whoever made it, unless another record of the same carrier is
 * bound to it already — a carrier never lists one town twice (decision of 02.11.2026), so two
 * namesakes of one region it tells apart only by a qualifier stay two towns: the leading carrier
 * makes the second, any other queues it. A hand-made town (decision 42) is bound to, not
 * duplicated, and a field an administrator set is never written over — the leading carrier only
 * fills in what nobody set by hand. A binding writes the carrier's spelling as an alias, so the
 * next carrier spelled alike matches at step 3. Duplicates are never merged here (decision 39).
 *
 * The matcher returns the binding and keeps no binding rows: those are the delivery module's
 * (`delivery_carrier_localities`), and its sync stores what comes back, page by page.
 */
final class Matcher
{
    public const float DEFAULT_RADIUS_KM = 5.0;

    /** @var array<string, array<string, GeoRegion>> country → key → region, for the page */
    private array $regions = [];

    /** @var array<string, array<string, list<int>>> country → key → locality ids, for the page */
    private array $byKey = [];

    /** @var array<int, GeoLocality> */
    private array $localities = [];

    /** @var array<int, array<string, true>> locality id → the name and alias keys it has */
    private array $keysOf = [];

    /** @var array<int, true> localities a record of the carrier being matched is bound to */
    private array $taken = [];

    public function __construct(
        private readonly LeadingCarriers $leaders,
        private readonly Config $config,
        private readonly Locales $locales,
        private readonly CarrierBindings $bindings,
    ) {}

    /** One record; a sync matches its pages with {@see matchPage()}. */
    public function match(string $carrier, LocalityRecord $record, ?MatchResult $current = null): MatchResult
    {
        $current = $current === null ? [] : [$record->externalId => $current];

        return $this->matchPage($carrier, [$record], $current)[$record->externalId];
    }

    /**
     * A page of a carrier's localities: the candidates of the whole page come in a handful of
     * queries, not a query per record (§7.3). The caller wraps the page in its transaction.
     *
     * @param  list<LocalityRecord>  $records
     * @param  array<array-key, MatchResult>  $current  the bindings stored so far, by external id
     * @return array<array-key, MatchResult> by external id — PHP turns a numeric id into an int key
     *
     * @throws GeographyException when a record has no name or no valid country — the
     *                            satellite's mistake, which its contract test catches
     */
    public function matchPage(string $carrier, array $records, array $current = []): array
    {
        $source = $this->source($carrier);
        $results = [];
        $todo = [];

        foreach ($records as $record) {
            $now = $current[$record->externalId] ?? null;

            if ($now !== null && $now->isSettled()) {
                $results[$record->externalId] = $now->keep();

                continue;
            }

            $this->check($record);
            $todo[] = $record;
        }

        if ($todo === []) {
            return $results;
        }

        $this->load($todo);
        $this->taken($carrier, $todo, $results);

        $leading = [];

        foreach ($todo as $record) {
            $country = $this->country($record);
            $leading[$country] ??= $this->leaders->claim($country, $carrier) === $carrier;

            $result = $this->matchOne($record, $source, $leading[$country]);
            $results[$record->externalId] = $result;

            if ($result->localityId !== null) {
                $this->taken[$result->localityId] = true;
            }
        }

        return $results;
    }

    /**
     * "Bind" in the queue: a person binds the record to a locality. Manual, so no sync moves it
     * again; the carrier's spellings of the town and of its region become aliases, and the next
     * record spelled alike — of this carrier or another — matches without a person.
     */
    public function bind(string $carrier, LocalityRecord $record, GeoLocality $locality): MatchResult
    {
        $source = $this->source($carrier);
        $this->check($record);

        return DB::transaction(function () use ($record, $locality, $source): MatchResult {
            $this->load([$record], $locality);

            $region = $locality->region_id === null ? null : $locality->region;

            if ($region instanceof GeoRegion) {
                $this->regionAliases($region, $this->regionSpellings($record), $source);
            }

            $this->aliases($locality, $record, $source);

            return MatchResult::matched($locality->id, BindingMethod::Manual);
        });
    }

    /**
     * "Make a shared locality" in the queue: a person decides the record is a town of its own.
     * The region is the one given, else the record's — found, or made when the country has no
     * such region yet.
     */
    public function create(string $carrier, LocalityRecord $record, ?GeoRegion $region = null): MatchResult
    {
        $source = $this->source($carrier);
        $this->check($record);

        return DB::transaction(function () use ($record, $region, $source): MatchResult {
            $this->load([$record]);

            if ($region === null && ($spellings = $this->regionSpellings($record)) !== []) {
                $region = $this->findRegion($this->country($record), $spellings)
                    ?? $this->createRegion($this->country($record), $spellings, $source);
            }

            if ($region !== null && $region->country_code !== $this->country($record)) {
                throw new GeographyException('The region is not one of the record\'s country.');
            }

            return MatchResult::matched($this->createLocality($record, $region, $source)->id, BindingMethod::Created);
        });
    }

    public function radiusKm(): float
    {
        $radius = $this->config->get('webx-geography.match_radius_km', self::DEFAULT_RADIUS_KM);

        return is_numeric($radius) && (float) $radius > 0 ? (float) $radius : self::DEFAULT_RADIUS_KM;
    }

    /** Kilometres along the ground between two points (haversine). */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // Steps

    private function matchOne(LocalityRecord $record, string $source, bool $leading): MatchResult
    {
        $country = $this->country($record);
        $spellings = $this->regionSpellings($record);
        $region = null;

        // Step 2. A record with no region at all is matched across the country: the carrier did
        // not divide it, and the namesakes, if any, are told apart by coordinates below.
        if ($spellings !== []) {
            $region = $this->findRegion($country, $spellings);

            if ($region === null) {
                if (! $leading) {
                    return MatchResult::pending(QueueReason::RegionUnknown);
                }

                // The leading carrier is the source of the country's shared records (§4.4):
                // without its regions a fresh site would queue every town it has.
                $region = $this->createRegion($country, $spellings, $source);
            } else {
                $this->regionAliases($region, $spellings, $source);
            }
        }

        $found = $this->candidates($record, $region, $spellings !== []);
        $kind = $this->kind($record);

        // Steps 3–4.
        $same = array_values(array_filter($found, fn (GeoLocality $locality): bool => $locality->kind === $kind
            && $this->districtFits($record, $locality)));

        if (count($same) === 1) {
            return $this->bound($same[0], BindingMethod::AutoExact, $record, $source, $leading);
        }

        if ($same !== []) {
            $near = $this->within($record, $same);

            return count($near) === 1
                ? $this->bound($near[0], BindingMethod::AutoCoords, $record, $source, $leading)
                : MatchResult::pending(QueueReason::Ambiguous, $this->offer($record, $same));
        }

        // Step 5.
        $other = array_values(array_filter($found, fn (GeoLocality $locality): bool => $locality->kind !== $kind
            && $this->districtFits($record, $locality)));

        if ($other !== []) {
            $near = $this->within($record, $other);

            return count($near) === 1
                ? $this->bound($near[0], BindingMethod::AutoCoords, $record, $source, $leading)
                : MatchResult::pending(QueueReason::KindMismatch, $this->offer($record, $other));
        }

        // Step 6.
        if ($leading) {
            return MatchResult::matched($this->createLocality($record, $region, $source)->id, BindingMethod::Created);
        }

        return MatchResult::pending(QueueReason::NotFound);
    }

    private function bound(GeoLocality $locality, BindingMethod $method, LocalityRecord $record, string $source, bool $leading): MatchResult
    {
        $this->aliases($locality, $record, $source);

        if ($leading) {
            $this->fillIn($locality, $record);
        }

        return MatchResult::matched($locality->id, $method);
    }

    /**
     * The leading carrier completes a record nobody set by hand: names in languages it lacks,
     * the district and coordinates it has none of. It never replaces a value — renaming a town is
     * a person's call — and a hand-made locality, or a field changed by hand, is left as it is.
     */
    private function fillIn(GeoLocality $locality, LocalityRecord $record): void
    {
        if (! $locality->isManual('names')) {
            $locality->names = [...$this->names($record), ...$locality->names];
        }

        $values = [
            'district' => $this->district($record),
            'lat' => $record->lat === null ? null : round($record->lat, 6),
            'lng' => $record->lng === null ? null : round($record->lng, 6),
        ];

        foreach ($values as $field => $value) {
            if ($value !== null && $locality->{$field} === null && ! $locality->isManual($field)) {
                $locality->{$field} = $value;
            }
        }

        if ($locality->isDirty()) {
            $locality->save();
            $this->remember($locality, array_map(static fn (string $name): string => Normalizer::key($name), array_values($locality->names)));
        }
    }

    // Loading the page

    /**
     * The regions and the localities every record of the page may land on: two queries for the
     * regions with their aliases, two for the localities with the keys they answer to.
     *
     * @param  list<LocalityRecord>  $records
     */
    private function load(array $records, ?GeoLocality $also = null): void
    {
        $this->regions = [];
        $this->byKey = [];
        $this->localities = [];
        $this->keysOf = [];

        $countries = [];
        $regionKeys = [];
        $keys = [];

        foreach ($records as $record) {
            $countries[$this->country($record)] = true;

            foreach ($this->regionSpellings($record) as $name) {
                $regionKeys[Normalizer::key($name)] = true;
            }

            foreach ($this->spellings($record) as $name) {
                $keys[Normalizer::key($name)] = true;
            }
        }

        $countries = array_keys($countries);
        $regionKeys = array_values(array_filter(array_map('strval', array_keys($regionKeys))));
        $keys = array_values(array_filter(array_map('strval', array_keys($keys))));

        if ($regionKeys !== []) {
            $regions = GeoRegion::query()
                ->with('aliases')
                ->whereIn('country_code', $countries)
                ->where(static function (Builder $query) use ($regionKeys): void {
                    $query->whereIn('name_key', $regionKeys)
                        ->orWhereIn('id', GeoRegionAlias::query()->select('region_id')->whereIn('name_key', $regionKeys));
                })
                ->orderBy('id')
                ->get();

            foreach ($regions as $region) {
                $this->regions[$region->country_code][$region->name_key] ??= $region;
            }

            // A region's own key wins over another region's alias spelled the same.
            foreach ($regions as $region) {
                foreach ($region->aliases as $alias) {
                    $this->regions[$region->country_code][$alias->name_key] ??= $region;
                }
            }
        }

        if ($keys !== []) {
            $rows = GeoLocalityName::query()
                ->toBase()
                ->select(['locality_id', 'country_code', 'name_key'])
                ->whereIn('country_code', $countries)
                ->whereIn('name_key', $keys)
                ->whereIn('kind', [GeoLocalityName::NAME, GeoLocalityName::ALIAS])
                ->get();

            foreach ($rows as $row) {
                $id = (int) $row->locality_id;
                $this->byKey[(string) $row->country_code][(string) $row->name_key][] = $id;
                $this->keysOf[$id][(string) $row->name_key] = true;
            }

            $ids = array_keys($this->keysOf);

            if ($ids !== []) {
                foreach (GeoLocality::query()->whereKey($ids)->get() as $locality) {
                    $this->localities[$locality->id] = $locality;
                }
            }
        }

        if ($also !== null) {
            $this->localities[$also->id] = $also;
            $this->keysOf[$also->id] ??= [];

            foreach (GeoLocalityName::query()->where('locality_id', $also->id)->whereIn('kind', [GeoLocalityName::NAME, GeoLocalityName::ALIAS])->pluck('name_key') as $key) {
                $this->keysOf[$also->id][(string) $key] = true;
            }
        }
    }

    /**
     * Localities of the record's country that answer to one of its spellings, in its region when
     * it has one.
     *
     * @return list<GeoLocality>
     */
    private function candidates(LocalityRecord $record, ?GeoRegion $region, bool $hasRegion): array
    {
        $country = $this->country($record);
        $ids = [];

        foreach ($this->spellings($record) as $name) {
            foreach ($this->byKey[$country][Normalizer::key($name)] ?? [] as $id) {
                $ids[$id] = true;
            }
        }

        $found = [];

        foreach (array_keys($ids) as $id) {
            $locality = $this->localities[$id] ?? null;

            if ($locality === null || $locality->country_code !== $country) {
                continue;
            }

            if ($hasRegion && $locality->region_id !== $region?->id) {
                continue;
            }

            if (isset($this->taken[$id])) {
                continue;
            }

            $found[] = $locality;
        }

        usort($found, static fn (GeoLocality $a, GeoLocality $b): int => $a->id <=> $b->id);

        return $found;
    }

    /**
     * The candidates within the radius, nearest first. Without coordinates on either side
     * nothing is near: the coordinates are what picks a namesake, not a guess.
     *
     * @param  list<GeoLocality>  $localities
     * @return list<GeoLocality>
     */
    private function within(LocalityRecord $record, array $localities): array
    {
        $radius = $this->radiusKm();
        $near = [];

        foreach ($localities as $locality) {
            $distance = $this->distanceTo($record, $locality);

            if ($distance !== null && $distance <= $radius) {
                $near[] = [$distance, $locality];
            }
        }

        usort($near, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(static fn (array $pair): GeoLocality => $pair[1], $near);
    }

    /**
     * @param  list<GeoLocality>  $localities
     * @return list<Candidate>
     */
    private function offer(LocalityRecord $record, array $localities): array
    {
        $candidates = array_map(fn (GeoLocality $locality): Candidate => new Candidate(
            $locality->id,
            ($distance = $this->distanceTo($record, $locality)) === null ? null : round($distance, 2),
        ), $localities);

        // Nearest first; those without coordinates last, by id.
        usort($candidates, static fn (Candidate $a, Candidate $b): int => [$a->distanceKm === null, $a->distanceKm, $a->localityId]
            <=> [$b->distanceKm === null, $b->distanceKm, $b->localityId]);

        return $candidates;
    }

    private function distanceTo(LocalityRecord $record, GeoLocality $locality): ?float
    {
        if ($record->lat === null || $record->lng === null || $locality->lat === null || $locality->lng === null) {
            return null;
        }

        return self::distanceKm($record->lat, $record->lng, $locality->lat, $locality->lng);
    }

    /** A district the carrier gave has to be the locality's, when the locality has one. */
    private function districtFits(LocalityRecord $record, GeoLocality $locality): bool
    {
        $district = $record->district === null ? '' : Normalizer::key($record->district);

        if ($district === '' || Names::clean($locality->district) === []) {
            return true;
        }

        foreach (Names::clean($locality->district) as $name) {
            if (Normalizer::key($name) === $district) {
                return true;
            }
        }

        return false;
    }

    /**
     * The page's localities the carrier has bound another record to: stored bindings in one
     * query, and those the records kept on this page hold.
     *
     * @param  list<LocalityRecord>  $todo
     * @param  array<array-key, MatchResult>  $kept
     */
    private function taken(string $carrier, array $todo, array $kept): void
    {
        $this->taken = [];

        foreach ($kept as $result) {
            if ($result->localityId !== null) {
                $this->taken[$result->localityId] = true;
            }
        }

        $ids = array_keys($this->localities);

        if ($ids === []) {
            return;
        }

        $except = array_map(static fn (LocalityRecord $record): string => (string) $record->externalId, $todo);

        foreach ($this->bindings->taken($carrier, $ids, $except) as $id) {
            $this->taken[$id] = true;
        }
    }

    // Regions

    /** @param  array<array-key, string>  $spellings */
    private function findRegion(string $country, array $spellings): ?GeoRegion
    {
        foreach ($spellings as $name) {
            $region = $this->regions[$country][Normalizer::key($name)] ?? null;

            if ($region !== null) {
                return $region;
            }
        }

        return null;
    }

    /** @param  array<array-key, string>  $spellings */
    private function createRegion(string $country, array $spellings, string $source): GeoRegion
    {
        GeoCountry::query()->firstOrCreate(['code' => $country], ['active' => false]);

        $names = array_filter($spellings, 'is_string', ARRAY_FILTER_USE_KEY);
        $region = GeoRegion::query()->create(['country_code' => $country, 'names' => $names ?: [$this->locales->defaultCode() => reset($spellings)]]);
        $this->regions[$country][$region->name_key] = $region;
        $this->regionAliases($region, $spellings, $source);

        return $region;
    }

    /**
     * Every spelling of the region a carrier used that the country does not know yet becomes an
     * alias of it (§4.1). A spelling that is another region's name or alias stays that region's.
     *
     * @param  array<array-key, string>  $spellings
     */
    private function regionAliases(GeoRegion $region, array $spellings, string $source): void
    {
        $country = $region->country_code;

        foreach ($spellings as $name) {
            $key = Normalizer::key($name);

            if ($key === '' || $key === $region->name_key || isset($this->regions[$country][$key])) {
                continue;
            }

            GeoRegionAlias::query()->insertOrIgnore([
                'region_id' => $region->id,
                'name_key' => $key,
                'source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->regions[$country][$key] = $region;
        }
    }

    // Localities

    private function createLocality(LocalityRecord $record, ?GeoRegion $region, string $source): GeoLocality
    {
        $country = $this->country($record);
        GeoCountry::query()->firstOrCreate(['code' => $country], ['active' => false]);
        $kind = LocalityKind::from($this->kind($record));

        $locality = GeoLocality::query()->create([
            'country_code' => $country,
            'region_id' => $region?->id,
            'kind' => $kind->value,
            'names' => $this->names($record),
            'district' => $this->district($record),
            'lat' => $record->lat === null ? null : round($record->lat, 6),
            'lng' => $record->lng === null ? null : round($record->lng, 6),
            'weight' => $kind->weight(),
            'created_by' => $source,
        ]);

        $this->localities[$locality->id] = $locality;
        $this->remember($locality, array_map(static fn (string $name): string => Normalizer::key($name), array_values($locality->names)));
        // A spelling the names did not keep — the carrier's `name` beside another main-language
        // name — is an alias, so that the record still finds its town next time.
        $this->aliases($locality, $record, $source);

        return $locality;
    }

    /** The record's spellings the locality does not answer to yet, as aliases of the carrier. */
    private function aliases(GeoLocality $locality, LocalityRecord $record, string $source): void
    {
        foreach ($this->spellings($record) as $name) {
            $key = Normalizer::key($name);

            if ($key === '' || isset($this->keysOf[$locality->id][$key])) {
                continue;
            }

            NameIndex::addAlias($locality, $name, $source);
            $this->remember($locality, [$key]);
        }
    }

    /**
     * The page learns what it just wrote: the names and aliases a locality made or aliased on this
     * page answers to, so that it is offered — or, bound already, left out — like a stored one.
     *
     * @param  list<string>  $keys
     */
    private function remember(GeoLocality $locality, array $keys): void
    {
        foreach ($keys as $key) {
            if ($key === '') {
                continue;
            }

            if (! isset($this->keysOf[$locality->id][$key])) {
                $this->byKey[$locality->country_code][$key][] = $locality->id;
            }

            $this->keysOf[$locality->id][$key] = true;
        }
    }

    // The record

    private function check(LocalityRecord $record): void
    {
        if (! GeoCountry::isValidCode($this->country($record))) {
            throw new GeographyException("Record {$record->externalId}: \"{$record->country}\" is not a country code.");
        }

        if ($this->spellings($record) === []) {
            throw new GeographyException("Record {$record->externalId}: no name.");
        }
    }

    private function source(string $carrier): string
    {
        // `created_by` and `source` are 32 characters wide, `carrier:` takes 8 of them.
        if (preg_match('/^[a-z0-9-]{1,24}$/', $carrier) !== 1) {
            throw new GeographyException("\"{$carrier}\" is not a carrier key: up to 24 of a-z, 0-9 and \"-\".");
        }

        return 'carrier:'.$carrier;
    }

    private function country(LocalityRecord $record): string
    {
        return strtoupper(trim($record->country));
    }

    /** A type the satellite did not map into the closed list counts as `other` (§4.2). */
    private function kind(LocalityRecord $record): string
    {
        return (LocalityKind::tryFrom($record->kind) ?? LocalityKind::Other)->value;
    }

    /**
     * The record's names by language; its `name` in the site's main language when the languages
     * do not carry it.
     *
     * @return array<string, string>
     */
    private function names(LocalityRecord $record): array
    {
        $names = Names::clean($record->names);
        $name = Names::clean(['_' => $record->name])['_'] ?? null;

        if ($name !== null && ! in_array($name, $names, true)) {
            $names = [$this->locales->defaultCode() => $name, ...$names];
        }

        return $names;
    }

    /**
     * Every way the record spells the town: its names and its `name`.
     *
     * @return list<string>
     */
    private function spellings(LocalityRecord $record): array
    {
        $all = [...array_values(Names::clean($record->names)), ...array_values(Names::clean(['_' => $record->name]))];

        return array_values(array_unique(array_filter($all, static fn (string $name): bool => Normalizer::key($name) !== '')));
    }

    /**
     * Every way the record spells its region, keyed by language where it has one: the region's
     * names, then its `region` under the main language (or by number when that language is taken).
     *
     * @return array<array-key, string>
     */
    private function regionSpellings(LocalityRecord $record): array
    {
        $names = array_filter(Names::clean($record->regionNames), static fn (string $name): bool => Normalizer::key($name) !== '');
        $region = Names::clean(['_' => (string) $record->region])['_'] ?? null;

        if ($region !== null && Normalizer::key($region) !== '' && ! in_array($region, $names, true)) {
            $default = $this->locales->defaultCode();
            if (isset($names[$default])) {
                $names[] = $region;
            } else {
                $names = [$default => $region, ...$names];
            }
        }

        return $names;
    }

    /** @return array<string, string>|null */
    private function district(LocalityRecord $record): ?array
    {
        $district = Names::clean(['_' => (string) $record->district])['_'] ?? null;

        return $district === null ? null : [$this->locales->defaultCode() => $district];
    }
}
