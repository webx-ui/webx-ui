<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/** Step 4 — discounts and surcharges: cart rules, coupons, later the checkout's shipping. */
interface AdjustmentSource
{
    /** @return list<Adjustment> */
    public function adjustments(CartContext $cart): array;
}
