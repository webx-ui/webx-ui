<?php

declare(strict_types=1);

namespace WebxUi\Orders\Data;

/** A line of the order; `qty` is absolute, a child's already multiplied by its parent. */
final readonly class ItemData
{
    public function __construct(
        public int $id,
        public ?int $parentId,
        public string $type,
        public int $refId,
        public ?string $sku,
        public string $name,
        public ?string $optionsLabel,
        public int $price,
        public int $qty,
        public int $total,
        public bool $shippable,
        public ?string $source,
        public int $refundedQty,
        /** The edit the line came with (§37.4); 0 — placed with the order. */
        public int $revision = 0,
        /** ISO 8601 — taken out by an edit, the units refunded of it left. */
        public ?string $removedAt = null,
    ) {}
}
