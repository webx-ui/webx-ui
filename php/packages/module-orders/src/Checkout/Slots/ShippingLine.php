<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Slots;

use InvalidArgumentException;

/**
 * The line «Delivery cost» of the column of the totals (spec §22.2, §23.1). Amounts are in minor
 * units of the cart's currency; the column formats them with `Money`, the slot does not.
 */
final readonly class ShippingLine
{
    public const string FIXED = 'fixed';

    public const string FREE = 'free';

    public const string CARRIER_RATES = 'carrier_rates';

    /**
     * @param  string  $kind  'fixed' | 'free' | 'carrier_rates'
     * @param  int|null  $amount  fixed: in the total already, as the delivery adjustment
     * @param  int|null  $was  free: crossed out
     * @param  int|null  $estimate  carrier_rates: "≈ …", not in the total
     */
    public function __construct(
        public string $kind,
        public ?int $amount = null,
        public ?int $was = null,
        public ?int $estimate = null,
    ) {
        if (! in_array($kind, [self::FIXED, self::FREE, self::CARRIER_RATES], true)) {
            throw new InvalidArgumentException("A delivery cost is fixed, free or at carrier rates, not \"{$kind}\".");
        }

        if ($kind === self::FIXED && $amount === null) {
            throw new InvalidArgumentException('A fixed delivery cost needs its amount.');
        }
    }
}
