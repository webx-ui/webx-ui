<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;

/**
 * An order was made (§7.2) — after the commit, so a listener never sees an order rolled back.
 * Cashback, letters and accounting react here; what must be atomic with the order is
 * `OrderPlacing`'s.
 */
final readonly class OrderPlaced implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public bool $awaitingPayment,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
