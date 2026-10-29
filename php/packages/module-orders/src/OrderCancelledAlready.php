<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * The order is cancelled, and what was asked does not go with it (§5.1, §5.5): money that came
 * to a cancelled order is a refund, not a payment — the payment module writes a `note()` and the
 * manager decides — and a cancelled order is neither received nor still awaiting payment.
 */
final class OrderCancelledAlready extends Refusal
{
    public function __construct(string $what)
    {
        parent::__construct('cancelled', "The order is cancelled: {$what} is refused.");
    }
}
