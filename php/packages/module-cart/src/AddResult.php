<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use WebxUi\Cart\Models\Cart;

/** What a batch did: the cart it went into, the lines written, and a token when the cart is new. */
final class AddResult
{
    /**
     * @param  list<int>  $added  ids of the lines written or added to
     * @param  string|null  $token  the new cart's token, for its cookie; null — the cart was there
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly array $added,
        public readonly ?string $token,
    ) {}
}
