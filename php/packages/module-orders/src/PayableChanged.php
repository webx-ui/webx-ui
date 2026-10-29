<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use WebxUi\Cart\Totals\CartState;

/**
 * The chain, counted again for the order, comes to another amount than the buyer saw (§6.2): no
 * order, and the fresh state for the checkout to show — what changed, and the new payable.
 * `payable` is compared, not `total`: it is what the buyer pays.
 */
final class PayableChanged extends Refusal
{
    public function __construct(public readonly CartState $state, public readonly int $expected)
    {
        parent::__construct('payable_changed', "The payable is {$state->payable}, not {$expected}.");
    }
}
