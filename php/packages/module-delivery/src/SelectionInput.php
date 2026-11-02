<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/** What the buyer entered for a method, unchecked: a point, or the parts of an address. */
final readonly class SelectionInput
{
    public function __construct(
        public ?int $point = null,
        public ?string $street = null,
        public ?string $streetId = null,
        public ?string $house = null,
        public ?string $apartment = null,
        public ?string $postcode = null,
        public ?string $note = null,
    ) {}

    /** @param  array<string, mixed>  $input  `point` and `address.*` of the request (§12.2) */
    public static function fromArray(array $input): self
    {
        $address = is_array($input['address'] ?? null) ? $input['address'] : [];
        $text = static function (mixed $value): ?string {
            if (! is_scalar($value)) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        return new self(
            point: is_numeric($input['point'] ?? null) ? (int) $input['point'] : null,
            street: $text($address['street'] ?? null),
            streetId: $text($address['street_id'] ?? null),
            house: $text($address['house'] ?? null),
            apartment: $text($address['apartment'] ?? null),
            postcode: $text($address['postcode'] ?? null),
            note: $text($address['note'] ?? null),
        );
    }
}
