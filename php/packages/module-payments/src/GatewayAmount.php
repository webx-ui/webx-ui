<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use InvalidArgumentException;
use WebxUi\Cart\Money;

/**
 * Amounts as gateways write them (spec §6.3): a decimal string in the currency's ISO 4217
 * exponent — "12.34" for 1234 euro cents, "1234" for 1234 yen, "1.234" for 1234 fils — and back,
 * never through a float. The exponent is the cart's (`Money::exponent()`).
 */
final class GatewayAmount
{
    /** 1234 EUR → "12.34"; 5 KWD → "0.005"; -150 → "-1.50". */
    public static function toDecimal(int $minor, string $currency): string
    {
        $exponent = self::exponent($currency);
        $digits = (string) abs($minor);

        if ($exponent > 0) {
            $digits = str_pad($digits, $exponent + 1, '0', STR_PAD_LEFT);
            $digits = substr($digits, 0, -$exponent).'.'.substr($digits, -$exponent);
        }

        return ($minor < 0 ? '-' : '').$digits;
    }

    /**
     * "12.34" EUR → 1234; "12.3" → 1230; "12" → 1200. More digits than the currency has is not an
     * amount of it: refused rather than rounded — a gateway that says 12.345 euros is wrong.
     *
     * @throws InvalidArgumentException
     */
    public static function fromDecimal(string|int $decimal, string $currency): int
    {
        $exponent = self::exponent($currency);
        $text = trim((string) $decimal);

        if (preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', $text, $match) !== 1) {
            throw new InvalidArgumentException("[{$text}] is not a decimal amount.");
        }

        $fraction = rtrim($match[3] ?? '', '0');

        if (strlen($fraction) > $exponent) {
            throw new InvalidArgumentException("[{$text}] has more digits than {$currency} has.");
        }

        $minor = (int) (ltrim($match[2], '0').str_pad($fraction, $exponent, '0'));

        return $match[1] === '-' ? -$minor : $minor;
    }

    public static function exponent(string $currency): int
    {
        return app(Money::class)->exponent(strtoupper($currency));
    }
}
