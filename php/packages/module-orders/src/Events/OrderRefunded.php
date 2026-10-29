<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Data\RefundData;

/**
 * A refund is recorded (§7.2, §9): the money went back outside the site, and the wallet gives
 * back the tenders of `refund`. `full` — it gave back money or a tender, and after it the money
 * and every tender are back; it comes with the milestone «refunded», which is set once.
 */
final readonly class OrderRefunded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public RefundData $refund,
        public bool $full,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
