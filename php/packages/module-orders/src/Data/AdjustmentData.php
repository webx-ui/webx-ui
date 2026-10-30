<?php

declare(strict_types=1);

namespace WebxUi\Orders\Data;

/** A discount or a surcharge of the order; `itemId` — of a line, null — of the order. */
final readonly class AdjustmentData
{
    /** @param  array<string, mixed>  $meta */
    public function __construct(
        public int $id,
        public ?int $itemId,
        public string $source,
        public string $label,
        public int $amount,
        public array $meta,
    ) {}
}
