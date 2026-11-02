<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoLocalityName;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Geography\Models\GeoRegionAlias;

/**
 * The geography kept by hand (decision 42): a shop with no carrier at all still needs towns.
 * The panel screens and the MCP tools are thin over this; every refusal is a
 * {@see GeographyException} whose message a person can act on.
 *
 * Locality data, as the screens and tools pass it:
 *
 * - `country` — ISO 3166-1 alpha-2 (creating only);
 * - `names` — locale → name, at least one; no type prefix ("Town D", not "t. Town D");
 * - `kind` — one of {@see LocalityKind};
 * - `region_id` — a region of that country, or null;
 * - `district` — locale → name, or null;
 * - `lat`, `lng` — or null; `weight` — the order in suggestions; `active`.
 */
final class Editor
{
    /** The fields of a locality an administrator may change, and so may mark as manual. */
    private const array LOCALITY_FIELDS = ['names', 'kind', 'region_id', 'district', 'lat', 'lng', 'weight', 'active'];

    public function __construct(private readonly LocalityReferences $references) {}

    // Countries

    /** @param  array<string, string>|null  $names  The site's own name, if `symfony/intl`'s will not do. */
    public function enableCountry(string $code, ?array $names = null, ?int $position = null): GeoCountry
    {
        $country = $this->country($code);
        $country->active = true;

        if ($names !== null) {
            $country->names = Names::clean($names) ?: null;
        }

        if ($position !== null) {
            $country->position = max(0, $position);
        }

        $country->save();

        return $country;
    }

    /** Off, not gone: its localities stay where points and orders point at them. */
    public function disableCountry(string $code): GeoCountry
    {
        $country = $this->country($code);
        $country->active = false;
        $country->save();

        return $country;
    }

    // Regions

    /** @param  array<string, string>  $names */
    public function createRegion(string $country, array $names, ?string $code = null): GeoRegion
    {
        $country = $this->country($country, activate: true);
        $names = $this->names($names);
        $this->assertRegionFree($country->code, $names);

        $region = new GeoRegion([
            'country_code' => $country->code,
            'names' => $names,
            'code' => $this->regionCode($code),
        ]);
        // A region made by hand is the administrator's through and through: a sync binds to it
        // and never renames it.
        $region->markManual(['code', 'names']);
        $region->save();

        return $region;
    }

    /** @param  array{names?: array<string, string>, code?: string|null}  $changes */
    public function updateRegion(GeoRegion $region, array $changes): GeoRegion
    {
        if (array_key_exists('names', $changes)) {
            $names = $this->names($changes['names']);
            $this->assertRegionFree($region->country_code, $names, $region->id);
            $region->names = $names;
        }

        if (array_key_exists('code', $changes)) {
            $region->code = $this->regionCode($changes['code']);
        }

        $region->markManual(array_values(array_intersect(['code', 'names'], array_keys($region->getDirty()))));
        $region->save();

        return $region;
    }

    public function deleteRegion(GeoRegion $region): void
    {
        if ($region->localities()->exists()) {
            throw new GeographyException('The region has localities. Move them to another region or delete them first.');
        }

        $region->delete();
    }

    // Localities

    /**
     * Localities that look like the one described: same country, region and type, and one of
     * the given names has the key of a name or alias of theirs. The same check a carrier's
     * record goes through (§4.3, step 3), so a hand-made town does not become a second one.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, GeoLocality>
     */
    public function similar(array $data, ?int $except = null): Collection
    {
        $country = strtoupper((string) ($data['country'] ?? ''));
        $keys = array_values(array_unique(array_filter(array_map(
            static fn (string $name): string => Normalizer::key($name),
            Names::clean(is_array($data['names'] ?? null) ? $data['names'] : []),
        ))));

        if ($country === '' || $keys === []) {
            return new Collection;
        }

        $regionId = isset($data['region_id']) ? (int) $data['region_id'] : null;

        return GeoLocality::query()
            ->with('region')
            ->where('country_code', $country)
            ->where('kind', (string) ($data['kind'] ?? ''))
            ->when(
                $regionId === null,
                static fn (Builder $query) => $query->whereNull('region_id'),
                static fn (Builder $query) => $query->where('region_id', $regionId),
            )
            ->when($except !== null, static fn (Builder $query) => $query->whereKeyNot($except))
            ->where(static function (Builder $query) use ($country, $keys): void {
                $query->whereIn('name_key', $keys)
                    ->orWhereIn('id', static fn ($names) => $names->select('locality_id')
                        ->from('geo_locality_names')
                        ->where('country_code', $country)
                        ->whereIn('name_key', $keys)
                        ->whereIn('kind', [GeoLocalityName::NAME, GeoLocalityName::ALIAS]));
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DuplicateLocality when a similar locality exists and `$force` is off
     */
    public function createLocality(array $data, bool $force = false): GeoLocality
    {
        $country = $this->country((string) ($data['country'] ?? ''), activate: true);
        $data['country'] = $country->code;
        $attributes = $this->localityAttributes($data, $country->code, creating: true);

        if (! $force && ($similar = $this->similar($data))->isNotEmpty()) {
            throw new DuplicateLocality($similar);
        }

        return DB::transaction(static function () use ($attributes, $country): GeoLocality {
            $locality = new GeoLocality([
                'weight' => LocalityKind::from($attributes['kind'])->weight(),
                ...$attributes,
                'country_code' => $country->code,
                'created_by' => GeoLocality::MANUAL,
            ]);
            $locality->save();

            return $locality;
        });
    }

    /**
     * A change by hand. On a record a carrier or the import brought, every changed field is
     * marked manual, and the next sync leaves it as the administrator set it (decision 42).
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws DuplicateLocality when the change makes it look like another locality
     */
    public function updateLocality(GeoLocality $locality, array $changes, bool $force = false): GeoLocality
    {
        $attributes = $this->localityAttributes($changes, $locality->country_code, creating: false);
        $locality->fill($attributes);

        if (! $force && $locality->isDirty(['names', 'kind', 'region_id'])) {
            $similar = $this->similar([
                'country' => $locality->country_code,
                'names' => $locality->names,
                'kind' => $locality->kind,
                'region_id' => $locality->region_id,
            ], except: $locality->id);

            if ($similar->isNotEmpty()) {
                throw new DuplicateLocality($similar);
            }
        }

        $changed = array_values(array_intersect(self::LOCALITY_FIELDS, array_keys($locality->getDirty())));

        if ($changed !== [] && $locality->created_by !== GeoLocality::MANUAL) {
            $locality->markManual($changed);
        }

        DB::transaction(static fn () => $locality->save());

        return $locality;
    }

    /**
     * Deleted when nothing holds it; otherwise switched off, so that it leaves the suggestions
     * while the points, selections and orders that name it keep their town.
     */
    public function removeLocality(GeoLocality $locality): Removal
    {
        if ($this->references->holding($locality) === []) {
            $locality->delete();

            return Removal::Deleted;
        }

        $this->updateLocality($locality, ['active' => false], force: true);

        return Removal::Deactivated;
    }

    public function deactivate(GeoLocality $locality): GeoLocality
    {
        return $this->updateLocality($locality, ['active' => false], force: true);
    }

    public function activate(GeoLocality $locality): GeoLocality
    {
        return $this->updateLocality($locality, ['active' => true], force: true);
    }

    // Checks

    private function country(string $code, bool $activate = false): GeoCountry
    {
        $code = strtoupper(trim($code));

        if (! GeoCountry::isValidCode($code)) {
            throw new GeographyException("\"{$code}\" is not a country code: two letters of ISO 3166-1, like \"DE\".");
        }

        $country = GeoCountry::query()->find($code);

        if ($country instanceof GeoCountry) {
            return $country;
        }

        // A country someone builds towns in by hand is one the shop works in; a carrier's
        // country, by contrast, waits to be turned on (§4.1).
        return GeoCountry::query()->create(['code' => $code, 'active' => $activate]);
    }

    /**
     * @param  mixed  $names
     * @return array<string, string>
     */
    private function names($names): array
    {
        $clean = Names::clean(is_array($names) ? $names : []);

        if ($clean === [] || Normalizer::key((string) Names::main($clean)) === '') {
            throw new GeographyException('A name is required, in at least one language of the site.');
        }

        return $clean;
    }

    private function regionCode(?string $code): ?string
    {
        $code = $code === null ? '' : strtoupper(trim($code));

        if ($code === '') {
            return null;
        }

        if (preg_match('/^[A-Z0-9]{1,8}$/', $code) !== 1) {
            throw new GeographyException('A region code is up to eight letters and digits of ISO 3166-2, without the country.');
        }

        return $code;
    }

    /** @param  array<string, string>  $names */
    private function assertRegionFree(string $country, array $names, ?int $except = null): void
    {
        $keys = array_values(array_unique(array_map(static fn (string $name): string => Normalizer::key($name), $names)));

        $taken = GeoRegion::query()
            ->where('country_code', $country)
            ->when($except !== null, static fn (Builder $query) => $query->whereKeyNot($except))
            ->where(static function (Builder $query) use ($keys): void {
                $query->whereIn('name_key', $keys)
                    ->orWhereIn('id', GeoRegionAlias::query()->select('region_id')->whereIn('name_key', $keys));
            })
            ->first();

        if ($taken instanceof GeoRegion) {
            throw new GeographyException("The country already has this region: \"{$taken->name()}\" (#{$taken->id}).");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function localityAttributes(array $data, string $country, bool $creating): array
    {
        $attributes = [];

        if ($creating || array_key_exists('names', $data)) {
            $attributes['names'] = $this->names($data['names'] ?? null);
        }

        if ($creating || array_key_exists('kind', $data)) {
            $kind = LocalityKind::tryFrom((string) ($data['kind'] ?? ''));

            if ($kind === null) {
                throw new GeographyException('The type of a locality is one of: '.implode(', ', LocalityKind::values()).'.');
            }

            $attributes['kind'] = $kind->value;
        }

        if ($creating || array_key_exists('region_id', $data)) {
            $regionId = isset($data['region_id']) && $data['region_id'] !== '' ? (int) $data['region_id'] : null;

            if ($regionId !== null && ! GeoRegion::query()->whereKey($regionId)->where('country_code', $country)->exists()) {
                throw new GeographyException('The region is not one of this country.');
            }

            $attributes['region_id'] = $regionId;
        }

        if (array_key_exists('district', $data)) {
            $attributes['district'] = Names::clean(is_array($data['district']) ? $data['district'] : []) ?: null;
        }

        foreach (['lat' => 90, 'lng' => 180] as $field => $bound) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field] === null || $data[$field] === '' ? null : $data[$field];

            if ($value !== null && (! is_numeric($value) || abs((float) $value) > $bound)) {
                throw new GeographyException("The {$field} is a number from -{$bound} to {$bound}, or empty.");
            }

            $attributes[$field] = $value === null ? null : round((float) $value, 6);
        }

        if (array_key_exists('weight', $data) && $data['weight'] !== null) {
            if (! is_numeric($data['weight']) || (int) $data['weight'] < 0) {
                throw new GeographyException('The weight is a whole number, zero or more.');
            }

            $attributes['weight'] = (int) $data['weight'];
        }

        if (array_key_exists('active', $data)) {
            $attributes['active'] = (bool) $data['active'];
        }

        return $attributes;
    }
}
