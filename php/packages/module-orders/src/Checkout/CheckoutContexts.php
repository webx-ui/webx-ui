<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;
use WebxUi\Orders\Checkout\Slots\SlotStep;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;

/**
 * The context of the current request (spec §20.2): the cart of `CartHandle` — the account's for a
 * signed-in visitor, the guest's by the cookie; the checkout never takes a cart's id from the
 * address or the body (§19). Counted once a request; a write of the request asks for a fresh one.
 */
final class CheckoutContexts
{
    private ?CheckoutContext $current = null;

    public function __construct(
        private readonly CartHandle $carts,
        private readonly Calculator $calculator,
        private readonly Container $container,
    ) {}

    public function current(): CheckoutContext
    {
        if ($this->current !== null) {
            return $this->current;
        }

        $locale = app()->getLocale();
        $cart = $this->carts->cart();
        $cart?->touchIfStale();
        $state = $cart !== null
            ? $this->calculator->calculate($cart, $locale, Purpose::Checkout)
            : $this->calculator->empty($locale);

        return $this->current = new CheckoutContext(
            cart: $cart,
            state: $state,
            draft: $cart !== null ? CheckoutDraft::query()->find($cart->id) : null,
            visitor: $this->container->bound(Visitors::class) ? $this->container->make(Visitors::class)->current() : null,
            locale: $locale,
            currency: $state->currency,
            shipping: $this->shippingChoice(...),
        );
    }

    /** The delivery step as the registry has it now — a project may have removed or replaced it. */
    private function shippingChoice(CheckoutContext $context): ?ShippingChoice
    {
        $step = $this->container->make(CheckoutSteps::class)->find(CheckoutSlots::DELIVERY, $context);

        return $step instanceof SlotStep ? $step->choice($context) : null;
    }

    /** Count again on the next ask: after a step was saved, before the page is drawn. */
    public function forget(): void
    {
        $this->current = null;
    }
}
