<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Data\RefundData;

/**
 * A refund recorded by mistake is reversed (decision 98, §9.5): what `refund` gave back counts no
 * more — the wallet takes its tenders back, the loyalty counts the cashback again. `reason` is
 * why; `milestoneRemoved` — the order is no longer given back whole and «refunded» came off.
 */
final readonly class OrderRefundReversed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public RefundData $refund,
        public ?string $reason,
        public bool $milestoneRemoved,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
