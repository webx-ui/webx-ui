<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/**
 * A street as the step shows it and a type is given it: a copy of a row of a carrier's directory
 * (§8). For the shop's courier a suggestion is only text — `streetId` is null: another carrier's
 * id means nothing to it (§8.3).
 */
final readonly class StreetRef
{
    public function __construct(
        /** The carrier's id of the street; null — a suggestion for the shop's courier. */
        public ?string $streetId,
        /** As shown in the language asked for: "Example street". */
        public string $name,
        public string $kind,
        /** The normalised name the prefix is matched on. */
        public string $key,
    ) {}

    /** @return array{id: string|null, name: string, kind: string} */
    public function toArray(): array
    {
        return ['id' => $this->streetId, 'name' => $this->name, 'kind' => $this->kind];
    }
}
