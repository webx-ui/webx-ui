<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Data\TenderData;

/**
 * The milestone «cancelled» (§7.2, §9.1). The tenders left on the order are marked given back in
 * the same transaction and come here as `tendersReturned` — the wallet returns them; money is
 * not given back by a cancellation: `wasPaid` tells the manager and the letters to see to it.
 */
final readonly class OrderCancelled implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public ?string $reason,
        public bool $wasPaid,
        /** @var list<TenderData> */
        public array $tendersReturned,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
