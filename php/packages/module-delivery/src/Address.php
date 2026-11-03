<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/** Where a courier goes, checked (§6.1). */
final readonly class Address
{
    public function __construct(
        /** As shown: "Example street". */
        public string $street,
        /** The carrier's street id from its directory; null — the shop's courier, typed by hand (decision 31). */
        public ?string $streetId,
        public string $house,
        public ?string $apartment = null,
        public ?string $postcode = null,
        /** "Entrance from the yard". */
        public ?string $note = null,
    ) {}

    /** @return array{street: string, street_id: string|null, house: string, apartment: string|null, postcode: string|null, note: string|null} */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'street_id' => $this->streetId,
            'house' => $this->house,
            'apartment' => $this->apartment,
            'postcode' => $this->postcode,
            'note' => $this->note,
        ];
    }
}
