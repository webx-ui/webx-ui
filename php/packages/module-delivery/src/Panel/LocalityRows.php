<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel;

use Illuminate\Support\Collection;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Names;

/**
 * A locality as every screen of the section shows it: the list of the geography, the popular
 * towns, the candidates of the binding queue and the locality fields of the forms.
 */
final class LocalityRows
{
    /**
     * Where a record came from, in the words of the filter (decision 42): `manual`, `import`, or
     * `carrier` for any of the carriers — the exact one stays in `created_by`.
     */
    public static function source(GeoLocality $locality): string
    {
        return match (true) {
            $locality->created_by === GeoLocality::MANUAL => 'manual',
            $locality->created_by === GeoLocality::IMPORT => 'import',
            default => 'carrier',
        };
    }

    /**
     * The short line of a picker or of a candidate.
     *
     * @return array{id: int, name: string, label: string, kind: string, country: string, active: bool}
     */
    public static function brief(GeoLocality $locality, string $locale): array
    {
        return [
            'id' => $locality->id,
            'name' => $locality->name($locale),
            'label' => $locality->label($locale),
            'kind' => $locality->kind,
            'country' => $locality->country_code,
            'active' => $locality->active,
        ];
    }

    /**
     * A row of the geography's list, with the carriers bound to it (the region is expected loaded).
     *
     * @param  Collection<int, DeliveryCarrierLocality>  $bindings
     * @return array<string, mixed>
     */
    public static function row(GeoLocality $locality, string $locale, Collection $bindings): array
    {
        return [
            ...self::brief($locality, $locale),
            'region' => $locality->region === null ? null : ['id' => $locality->region->id, 'name' => $locality->region->name($locale)],
            'district' => Names::pick($locality->district, $locale),
            'weight' => $locality->weight,
            'created_by' => $locality->created_by,
            'source' => self::source($locality),
            'bindings' => $bindings
                ->map(static fn (DeliveryCarrierLocality $binding): array => [
                    'carrier' => $binding->carrier,
                    'external_id' => $binding->external_id,
                    'status' => $binding->status,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Everything the form of one locality edits, with the fields changed by hand on a carrier's
     * record (§4.6): the form marks them.
     *
     * @return array<string, mixed>
     */
    public static function values(GeoLocality $locality): array
    {
        return [
            'names' => $locality->names,
            'kind' => $locality->kind,
            'country' => $locality->country_code,
            'region_id' => $locality->region_id,
            'lat' => $locality->lat,
            'lng' => $locality->lng,
            'weight' => $locality->weight,
            'active' => $locality->active,
        ];
    }
}
