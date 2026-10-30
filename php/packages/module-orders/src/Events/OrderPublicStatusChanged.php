<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Data\PublicStatusData;

/**
 * The status the buyer sees changed (§7.2, decision 12) — right after `OrderStatusChanged`. The
 * buyer is written to here, through the channels of the new public status; a move inside one
 * group does not come here.
 */
final readonly class OrderPublicStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public PublicStatusData $from,
        public PublicStatusData $to,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
