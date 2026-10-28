<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

use WebxUi\Cart\PurchasableLine;

/**
 * A line as the chain sees it: what is stored, or what a rule generated, with the provider's
 * answer beside it. Sources read these; nobody writes them back.
 */
final class ResolvedLine
{
    /**
     * @param  int|string  $key  the stored line's id, or "rule#12:0" for a generated one
     * @param  int  $effectiveQty  qty × the parent's effective qty, capped by maxQty
     * @param  PurchasableLine|null  $line  null — the record or its provider is gone
     * @param  string|null  $source  the generating rule; null — a stored line
     * @param  array<string, mixed>|null  $options
     * @param  string  $name  the provider's name, or the snapshot's when gone
     * @param  int|null  $price  the unit price that counts; null when gone
     * @param  bool  $available  counts in the sum
     */
    public function __construct(
        public readonly int|string $key,
        public readonly string $type,
        public readonly int $refId,
        public readonly int $qty,
        public readonly int $effectiveQty,
        public readonly ?int $parentId,
        public readonly ?PurchasableLine $line,
        public readonly ?string $source,
        public readonly ?array $options,
        public readonly string $name,
        public readonly ?int $price,
        public readonly ?int $priceAtAdd,
        public readonly bool $available,
        public readonly bool $declinable = false,
        public readonly ?string $label = null,
    ) {}

    public function gone(): bool
    {
        return $this->line === null;
    }

    public function generated(): bool
    {
        return $this->source !== null;
    }

    /** The line's part of the subtotal: nothing for a line that cannot be bought. */
    public function amount(): int
    {
        return $this->available && $this->price !== null ? $this->price * $this->effectiveQty : 0;
    }
}
