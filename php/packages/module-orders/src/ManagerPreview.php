<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use WebxUi\Cart\Totals\CartState;

/**
 * The order a manager's lines would make, counted and not placed (`Orders::previewByManager()`):
 * the count `place()` would freeze, and the refusal it would give, if any.
 */
final readonly class ManagerPreview
{
    public function __construct(
        public CartState $state,
        public ?CartNotOrderable $refusal = null,
    ) {}

    public function orderable(): bool
    {
        return $this->refusal === null;
    }
}
