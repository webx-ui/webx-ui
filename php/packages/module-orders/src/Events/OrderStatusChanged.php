<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Data\StatusData;

/** The internal status changed (§7.2) — a working stage; the buyer may see nothing of it. */
final readonly class OrderStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public StatusData $from,
        public StatusData $to,
        public bool $publicChanged,
        public ?string $note,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
