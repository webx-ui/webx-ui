<?php

declare(strict_types=1);

namespace WebxUi\Cart\Events;

/**
 * The guest's cart is about to join the account's at sign-in and then go (§13.3 of the visitors
 * spec). Dispatched synchronously inside the merge's transaction, while the guest's row still
 * stands: whatever a neighbour keeps by the cart — the checkout's draft — moves over now, or goes
 * with the guest's cart by the cascade. A listener that throws rolls the sign-in's merge back.
 */
final readonly class CartsMerging
{
    public function __construct(
        public int $guestCartId,
        public int $accountCartId,
    ) {}
}
