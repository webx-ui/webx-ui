<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel;

use Closure;
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
     * @return array{id: int, name: string, label: string, kind: string, kind_label: string, country: string, active: bool}
     */
    public static function brief(GeoLocality $locality, string $locale): array
    {
        return [
            'id' => $locality->id,
            'name' => $locality->name($locale),
            'label' => $locality->label($locale),
            'kind' => $locality->kind,
            'kind_label' => self::kindLabel($locality->kind, $locale),
            'country' => $locality->country_code,
            'active' => $locality->active,
        ];
    }

    /** «Town», «Village»: the type of a locality in words, as the form's select names it. */
    public static function kindLabel(string $kind, string $locale): string
    {
        $key = 'webx-delivery::delivery.locality_kinds.'.$kind;
        $line = trans($key, [], $locale);

        return is_string($line) && $line !== $key ? $line : $kind;
    }

    /**
     * A row of the geography's list, with the carriers bound to it (the region is expected loaded),
     * each named by its title — a carrier's key is not a word anybody reads.
     *
     * @param  Collection<int, DeliveryCarrierLocality>  $bindings
     * @param  Closure(string): string  $carrierTitle
     * @return array<string, mixed>
     */
    public static function row(GeoLocality $locality, string $locale, Collection $bindings, Closure $carrierTitle): array
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
                    'carrier_title' => $carrierTitle($binding->carrier),
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
