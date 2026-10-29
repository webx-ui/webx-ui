<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use RuntimeException;

/**
 * What `Orders` answers when it will not do what was asked: nothing was written, and `reason` is
 * a stable code for the checkout, the panel and an agent to tell the cases apart by.
 */
abstract class Refusal extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
