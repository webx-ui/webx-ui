<?php

declare(strict_types=1);

namespace WebxUi\Orders\Data;

/** A payment made in advance — a certificate, cashback — and how much of it went back. */
final readonly class TenderData
{
    /** @param  array<string, mixed>  $meta  the wallet's batches: what it gives back the spent from */
    public function __construct(
        public int $id,
        public string $source,
        public string $label,
        public int $amount,
        public int $refundedAmount,
        public array $meta,
    ) {}
}
