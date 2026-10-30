<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;

/** The milestone «received» (§7.2): loyalty starts counting the days of its cashback. */
final readonly class OrderReceived implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        /** The waybill, when a delivery service set it. */
        public ?string $reference,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
