<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

use WebxUi\Geography\Matcher;

/**
 * A locality as a carrier has it, already normalised by the satellite (§4.2, §7.2): the name
 * without a type prefix, the type in its own field, the region by name.
 *
 * The geography owns the shape because the matcher reads it; the delivery sync hands its pages
 * of these to {@see Matcher}. `features`, `postcode` and `meta` are carried
 * for the binding row and never read here. New fields go last and optional.
 */
final readonly class LocalityRecord
{
    /**
     * @param  array<string, string>|null  $names  locale → name, if the carrier gives languages
     * @param  array<string, string>|null  $regionNames  locale → region name
     * @param  list<string>  $features  what the carrier does in the place: `courier`
     * @param  array<string, mixed>  $meta  the carrier's own words for the kind, its codes
     */
    public function __construct(
        public string $externalId,
        public string $country,
        public string $name,
        public string $kind,
        public ?string $region = null,
        public ?string $district = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?array $names = null,
        public ?array $regionNames = null,
        public array $features = [],
        public ?string $postcode = null,
        public array $meta = [],
    ) {}

    /**
     * The record back from the binding row's `record` column, so that the queue can match it
     * again after a person added a region or a town.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $strings = static fn (mixed $value): ?array => is_array($value)
            ? array_filter($value, static fn (mixed $item, mixed $key): bool => is_string($key) && is_string($item), ARRAY_FILTER_USE_BOTH)
            : null;
        $float = static fn (mixed $value): ?float => is_numeric($value) ? (float) $value : null;
        $string = static fn (mixed $value): ?string => is_scalar($value) && (string) $value !== '' ? (string) $value : null;

        return new self(
            externalId: (string) ($data['externalId'] ?? ''),
            country: (string) ($data['country'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            kind: (string) ($data['kind'] ?? ''),
            region: $string($data['region'] ?? null),
            district: $string($data['district'] ?? null),
            lat: $float($data['lat'] ?? null),
            lng: $float($data['lng'] ?? null),
            names: $strings($data['names'] ?? null),
            regionNames: $strings($data['regionNames'] ?? null),
            features: array_values(array_filter(is_array($data['features'] ?? null) ? $data['features'] : [], 'is_string')),
            postcode: $string($data['postcode'] ?? null),
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'externalId' => $this->externalId,
            'country' => $this->country,
            'name' => $this->name,
            'kind' => $this->kind,
            'region' => $this->region,
            'district' => $this->district,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'names' => $this->names,
            'regionNames' => $this->regionNames,
            'features' => $this->features,
            'postcode' => $this->postcode,
            'meta' => $this->meta,
        ];
    }
}
