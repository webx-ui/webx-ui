<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/** Step 7 — privileges other modules ask for by key: «the store pays the shipping». */
interface BenefitSource
{
    /** @return list<Benefit> */
    public function benefits(CartContext $cart): array;
}
