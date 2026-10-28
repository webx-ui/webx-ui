<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

final class Tender
{
    /**
     * @param  string  $source  'wallet'
     * @param  int  $amount  minor units, > 0, taken off the total
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $source,
        public readonly string $label,
        public readonly int $amount,
        public readonly array $meta = [],
    ) {}

    public function withAmount(int $amount): self
    {
        return new self($this->source, $this->label, $amount, $this->meta);
    }
}
