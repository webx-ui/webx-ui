<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Data\RevisionData;

/**
 * The lines of the order were edited (§39): the letter to the buyer when `notify`, the payment's
 * try for the old sum closed, the waybill noted, the cashback counted again. `balance` is the one
 * after the edit.
 */
final readonly class OrderItemsChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public RevisionData $revision,
        public Balance $balance,
        public bool $notify,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
