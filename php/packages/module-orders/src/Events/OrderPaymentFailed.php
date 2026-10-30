<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;

/**
 * A payment try did not go through (§5.4) — not a milestone: there may be several. The order
 * keeps awaiting payment; the manager is told.
 */
final readonly class OrderPaymentFailed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public ?string $reason,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
