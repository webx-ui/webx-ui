<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/**
 * Why the chain is counted: a wallet previews on Cart and Checkout and spends on Order, and an
 * order is never made from a chain with a source missing.
 */
enum Purpose: string
{
    case Cart = 'cart';
    case Checkout = 'checkout';
    case Order = 'order';
}
