<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/** Step 5 — what pays part of the total before the buyer does: certificates, cashback. */
interface TenderSource
{
    /** @return list<Tender> $cart->total is known here */
    public function tenders(CartContext $cart): array;
}
