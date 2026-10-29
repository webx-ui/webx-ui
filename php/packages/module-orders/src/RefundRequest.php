<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * Money and tenders given back outside the site, as the manager splits them (§9.3, decision 26):
 * nothing is counted for them — the amount in money and the amount of each tender are typed in,
 * and `refund()` checks them against what is left.
 */
final readonly class RefundRequest
{
    /**
     * @param  int  $amount  money back, minor units; 0 — only tenders or units
     * @param  array<int, int>  $tenders  tender id → amount back to the wallet
     * @param  array<int, int>  $items  item id → qty returned; empty — a money-only refund
     * @param  string|null  $reference  the bank's or ledger's operation number, if known
     */
    public function __construct(
        public int $amount,
        public array $tenders = [],
        public array $items = [],
        public ?string $reference = null,
        public ?string $reason = null,
        public string $source = 'panel',
    ) {}
}
