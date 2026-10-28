<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/** Step 7 — words beside the totals: «N more to free shipping». */
interface HintSource
{
    /** @return list<Hint> */
    public function hints(CartContext $cart): array;
}
