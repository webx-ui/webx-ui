<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Summary;

use Illuminate\Contracts\Support\Htmlable;
use WebxUi\Orders\Checkout\CheckoutContext;

/**
 * A part of the column of the totals a neighbour adds (spec §23.3): the field of a promo code, a
 * sum from a wallet. It changes the cart only through its own API and then dispatches
 * `webx:checkout:slot-changed` with its key as `slot`, and the checkout redraws the column.
 */
interface SummaryPart
{
    public function applies(CheckoutContext $context): bool;

    public function render(CheckoutContext $context): Htmlable;
}
