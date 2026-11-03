<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/** One line for the gateway's receipt (spec §6.3, decision 43): what the order's snapshot says, nothing counted here. */
final readonly class PaymentLine
{
    public function __construct(
        public string $name,
        public int $qty,
        /** Minor units, after the line's discounts. */
        public int $unitPrice,
        /** qty × unitPrice as the order keeps it. */
        public int $total,
        public ?string $sku = null,
    ) {}
}
