<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/** The order is paid, and what was asked makes no sense for a paid one (§5.5). */
final class OrderPaidAlready extends Refusal
{
    public function __construct(string $what)
    {
        parent::__construct('paid', "The order is paid: {$what} is refused.");
    }
}
