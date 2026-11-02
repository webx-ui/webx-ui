<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

/** A shared locality the queue offers a person for a carrier's record, with how far it lies. */
final readonly class Candidate
{
    public function __construct(
        public int $localityId,
        /** Kilometres between the record and the locality; null when either has no coordinates. */
        public ?float $distanceKm = null,
    ) {}

    /** @return array{locality_id: int, distance_km: float|null} */
    public function toArray(): array
    {
        return ['locality_id' => $this->localityId, 'distance_km' => $this->distanceKm];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['locality_id'] ?? 0),
            is_numeric($data['distance_km'] ?? null) ? (float) $data['distance_km'] : null,
        );
    }
}
