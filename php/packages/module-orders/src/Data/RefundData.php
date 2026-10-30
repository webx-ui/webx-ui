<?php

declare(strict_types=1);

namespace WebxUi\Orders\Data;

use WebxUi\Orders\Models\OrderRefund;

/** A refund as `OrderRefunded` carries it: what went back in money, to each tender, which units. */
final readonly class RefundData
{
    /**
     * @param  list<array{tender_id: int, amount: int}>  $tenders
     * @param  list<array{item_id: int, qty: int}>  $items
     */
    public function __construct(
        public int $id,
        public int $amount,
        public array $tenders,
        public array $items,
        public string $method,
        public ?string $reference,
        public ?string $reason,
    ) {}

    public static function of(OrderRefund $refund): self
    {
        return new self($refund->id, $refund->amount, $refund->tenders, $refund->items, $refund->method, $refund->reference, $refund->reason);
    }
}
