<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Closure;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;

/**
 * One request of the checkout as the steps see it (spec §20.2): the visitor's own cart, counted
 * once with `Purpose::Checkout`, its draft, the signed-in visitor. Steps read the cookie and the
 * session only through it.
 */
final class CheckoutContext
{
    /** @var array{0: ShippingChoice|null}|null asked once a request */
    private ?array $choice = null;

    /**
     * @param  Cart|null  $cart  null — the visitor has no cart yet; the state is then empty
     * @param  CheckoutDraft|null  $draft  null — nothing was saved yet: showing the page makes none (§21.1)
     * @param  (Closure(self): ?ShippingChoice)|null  $shipping  the delivery slot's choice; null — no slot
     */
    public function __construct(
        public readonly ?Cart $cart,
        public readonly CartState $state,
        public readonly ?CheckoutDraft $draft,
        public readonly ?Visitor $visitor,
        public readonly string $locale,
        public readonly ?string $currency,
        private readonly ?Closure $shipping = null,
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

    /**
     * The chosen delivery in neutral words, for the payment step (§22.2): null — no delivery slot,
     * the step is not needed, or nothing is chosen yet. Asked of the slot once a request.
     */
    public function shippingChoice(): ?ShippingChoice
    {
        if ($this->choice === null) {
            $this->choice = [$this->shipping !== null ? ($this->shipping)($this) : null];
        }

        return $this->choice[0];
    }
}
