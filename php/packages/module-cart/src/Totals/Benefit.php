<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

final class Benefit
{
    /** @param  string  $key  'shipping.paid_by_store' — what other modules ask for */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $source,
    ) {}
}
