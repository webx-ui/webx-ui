<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

final class Adjustment
{
    /**
     * @param  string  $source  'rule#12', 'coupon#SPRING'
     * @param  string  $label  in the cart's language
     * @param  int  $amount  minor units; < 0 a discount, > 0 a surcharge
     * @param  int|string|null  $line  a line it belongs to; null — the cart
     * @param  array<string, mixed>  $meta  for the order snapshot and MCP, not for the sum
     */
    public function __construct(
        public readonly string $source,
        public readonly string $label,
        public readonly int $amount,
        public readonly int|string|null $line = null,
        public readonly array $meta = [],
    ) {}
}
