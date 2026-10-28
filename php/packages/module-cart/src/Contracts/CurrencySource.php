<?php

declare(strict_types=1);

namespace WebxUi\Cart\Contracts;

use WebxUi\Cart\CurrencyFormat;

/** The site's currency. A currencies module will bind its own; the cart only asks. */
interface CurrencySource
{
    /** ISO 4217, upper case; null — not configured. */
    public function currency(): ?string;

    /** How an amount in this currency is written — the same in every language of the site. */
    public function format(string $currency): CurrencyFormat;
}
