<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Warning;

/**
 * The cart cannot become an order as it is (§6.2): it is empty, it is in another currency than
 * the site's, or some of its lines cannot be bought. Lines are never dropped quietly — the
 * warnings of the cart name them, for the checkout to show the buyer.
 */
final class CartNotOrderable extends Refusal
{
    public const string EMPTY = 'empty';

    public const string CURRENCY = 'currency';

    public const string LINES = 'lines';

    /** The warnings of a line that keep the cart from being ordered; a price that moved does not. */
    public const array BLOCKING = [
        Warning::LINE_GONE,
        Warning::LINE_UNAVAILABLE,
        Warning::LINE_CURRENCY,
        Warning::LINE_QTY_LIMITED,
    ];

    /** @param  list<Warning>  $warnings  the lines that cannot be bought; empty for the other reasons */
    public function __construct(string $reason, public readonly CartState $state, public readonly array $warnings = [])
    {
        parent::__construct($reason, "The cart cannot be ordered: {$reason}.");
    }
}
