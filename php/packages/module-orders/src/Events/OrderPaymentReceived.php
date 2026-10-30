<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Data\OrderData;

/**
 * Money came in for an order that is paid already — a top-up after an edit (§36.3, §39). Not
 * `OrderPaid`: the milestone is set once (decision 24), and its listeners wait for the first
 * payment. `balance` is the one after it.
 */
final readonly class OrderPaymentReceived implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public int $amount,
        public ?string $reference,
        public Balance $balance,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
