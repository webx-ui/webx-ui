<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Totals\ResolvedLine;

/**
 * What an edit of the lines would write (§37.1), counted once for the dry run and the edit alike:
 * the answer, and the rows behind it.
 *
 * @internal
 */
final readonly class ItemsPlan
{
    /**
     * @param  array<int, array{qty: int, adjustments_total: int, total: int, removed: bool}>  $items  the lines that change, by id — children too
     * @param  array<int, int>  $amounts  the line discounts that change, adjustment id → the new amount
     * @param  list<array{line: ResolvedLine, children: list<ResolvedLine>}>  $lines  the lines added, as the cart of the edit counted them
     * @param  Cart|null  $cart  the cart of the lines added, to go once they are written
     * @param  int  $position  where the lines added start
     */
    public function __construct(
        public ItemsEditResult $result,
        public array $items = [],
        public array $amounts = [],
        public array $lines = [],
        public ?Cart $cart = null,
        public int $position = 0,
    ) {}
}
