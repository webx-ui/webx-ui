<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;

/**
 * One request of the checkout as the steps see it (spec §20.2): the visitor's own cart, counted
 * once with `Purpose::Checkout`, its draft, the signed-in visitor. Steps read the cookie and the
 * session only through it.
 */
final readonly class CheckoutContext
{
    /**
     * @param  Cart|null  $cart  null — the visitor has no cart yet; the state is then empty
     * @param  CheckoutDraft|null  $draft  null — nothing was saved yet: showing the page makes none (§21.1)
     */
    public function __construct(
        public ?Cart $cart,
        public CartState $state,
        public ?CheckoutDraft $draft,
        public ?Visitor $visitor,
        public string $locale,
        public ?string $currency,
    ) {}

    public function isEmpty(): bool
    {
        return $this->cart === null || $this->state->isEmpty();
    }

    /**
     * What the buyer saved for a key of the draft (`customer`, `recipient`, `comment`), or null.
     */
    public function saved(string $key): mixed
    {
        return $this->draft?->getAttribute($key);
    }
}
