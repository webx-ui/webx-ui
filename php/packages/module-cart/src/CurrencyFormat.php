<?php

declare(strict_types=1);

namespace WebxUi\Cart;

/** How an amount in one currency is written: the same string in every language of the site. */
final class CurrencyFormat
{
    /**
     * @param  string  $symbol  "€", "$", or the code itself: "XYZ"
     * @param  bool  $symbolFirst  "$49.99" — true; "49,99 €" — false
     * @param  bool  $spaced  a no-break space between the symbol and the number
     * @param  string  $thousands  "\u{00A0}", ",", ".", "'"
     * @param  string  $decimal  "," or "."
     * @param  int  $exponent  ISO 4217 minor digits: 2; 0 for JPY; 3 for KWD
     */
    public function __construct(
        public readonly string $symbol,
        public readonly bool $symbolFirst,
        public readonly bool $spaced,
        public readonly string $thousands,
        public readonly string $decimal,
        public readonly int $exponent,
    ) {}
}
