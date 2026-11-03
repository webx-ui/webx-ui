<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Geography\Names;

/** A point of a carrier or of the shop, as a method type is given it: a copy of the row (§6.1). */
final readonly class PointRef
{
    /**
     * @param  array<string, string>|null  $titles
     * @param  array<string, string>  $address
     */
    public function __construct(
        public int $id,
        public string $carrier,
        public ?string $externalId,
        public string $kind,
        public ?int $localityId,
        public ?string $number,
        public ?array $titles,
        public array $address,
        public bool $active,
        public bool $removed,
    ) {}

    public static function of(DeliveryPoint $point): self
    {
        return new self(
            id: $point->id,
            carrier: $point->carrier,
            externalId: $point->external_id,
            kind: $point->kind,
            localityId: $point->locality_id,
            number: $point->number,
            titles: $point->titles,
            address: $point->address,
            active: $point->active,
            removed: $point->removed_at !== null,
        );
    }

    public function title(?string $locale = null): ?string
    {
        return Names::pick($this->titles, $locale);
    }

    public function address(?string $locale = null): string
    {
        return (string) Names::pick($this->address, $locale);
    }
}
