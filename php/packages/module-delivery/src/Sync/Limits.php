<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

/**
 * What a point takes (§7.4). Stored and shown in v1, not checked: the weight and size of an
 * order are not known (decision 4), and checking the value and cash on delivery is deferred.
 */
final readonly class Limits
{
    public function __construct(
        public ?int $maxWeightG = null,
        public ?int $maxLengthMm = null,
        public ?int $maxWidthMm = null,
        public ?int $maxHeightMm = null,
        /** Declared value, in minor units. */
        public ?int $maxValue = null,
        /** Whether it takes cash on delivery. */
        public ?bool $cod = null,
    ) {}

    /** @param  array<string, mixed>|null  $data */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        $int = static fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;

        return new self(
            $int('max_weight_g'),
            $int('max_length_mm'),
            $int('max_width_mm'),
            $int('max_height_mm'),
            $int('max_value'),
            is_bool($data['cod'] ?? null) ? $data['cod'] : null,
        );
    }

    /** @return array<string, int|bool> what was said, nothing else */
    public function toArray(): array
    {
        return array_filter([
            'max_weight_g' => $this->maxWeightG,
            'max_length_mm' => $this->maxLengthMm,
            'max_width_mm' => $this->maxWidthMm,
            'max_height_mm' => $this->maxHeightMm,
            'max_value' => $this->maxValue,
            'cod' => $this->cod,
        ], static fn (int|bool|null $value): bool => $value !== null);
    }
}
