<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Names;

/**
 * A locality of the shared geography as a method type is given it: a copy, never the model, so
 * that a satellite cannot reach the database through it (§6.1).
 */
final readonly class LocalityRef
{
    /**
     * @param  array<string, string>  $names  by language
     * @param  array<string, string>|null  $regionNames
     */
    public function __construct(
        public int $id,
        public string $country,
        public string $kind,
        public array $names,
        public ?int $regionId = null,
        public ?array $regionNames = null,
    ) {}

    public static function of(GeoLocality $locality): self
    {
        return new self(
            id: $locality->id,
            country: $locality->country_code,
            kind: $locality->kind,
            names: $locality->names,
            regionId: $locality->region_id,
            regionNames: $locality->region?->names,
        );
    }

    public function name(?string $locale = null): string
    {
        return (string) Names::pick($this->names, $locale);
    }

    /** «Town D, Region B». */
    public function label(?string $locale = null): string
    {
        $region = Names::pick($this->regionNames, $locale);

        return $region === null ? $this->name($locale) : $this->name($locale).', '.$region;
    }
}
