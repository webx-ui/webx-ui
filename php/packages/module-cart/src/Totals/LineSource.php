<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/** Step 2 — lines a rule adds while counting: a gift, «buy X, get Y». Never stored. */
interface LineSource
{
    /** @return list<GeneratedLine> */
    public function lines(CartContext $cart): array;
}
