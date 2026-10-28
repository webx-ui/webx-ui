<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use InvalidArgumentException;
use WebxUi\Cart\Contracts\CurrencySource;

/**
 * Amounts are integers in minor units everywhere; this is the one place that writes them as text
 * and reads a decimal into them. The rules come from the currency source, never from the
 * language of the request: a visitor reading in English and one reading in Ukrainian see the
 * same "49,99 €".
 */
final class Money
{
    private const string NBSP = "\u{00A0}";

    /** @var array<string, CurrencyFormat> */
    private array $formats = [];

    public function __construct(private readonly CurrencySource $currencies) {}

    public function format(int $minor, string $currency): string
    {
        $rules = $this->rules($currency);
        $digits = (string) abs($minor);

        if ($rules->exponent > 0) {
            $digits = str_pad($digits, $rules->exponent + 1, '0', STR_PAD_LEFT);
            $whole = substr($digits, 0, -$rules->exponent);
            $fraction = $rules->decimal.substr($digits, -$rules->exponent);
        } else {
            $whole = $digits;
            $fraction = '';
        }

        $number = $this->group($whole, $rules->thousands).$fraction;
        $gap = $rules->spaced ? self::NBSP : '';
        $written = $rules->symbolFirst ? $rules->symbol.$gap.$number : $number.$gap.$rules->symbol;

        return $minor < 0 ? '-'.$written : $written;
    }

    /** The format, or null for a null amount — a sum the answer does not have has no string. */
    public function formatOrNull(?int $minor, ?string $currency): ?string
    {
        return $minor === null || $currency === null ? null : $this->format($minor, $currency);
    }

    /**
     * A decimal — "1234.5", as a `decimal` column gives it — in minor units of the currency,
     * without a float on the way: 0.1 + 0.2 is not a price. Digits past the exponent round half
     * away from zero.
     */
    public function toMinor(int|string $amount, string $currency): int
    {
        $exponent = $this->exponent($currency);

        if (is_int($amount)) {
            return $amount * 10 ** $exponent;
        }

        if (preg_match('/^\s*(-)?(\d*)(?:\.(\d*))?\s*$/', $amount, $match) !== 1 || ($match[2] === '' && ($match[3] ?? '') === '')) {
            throw new InvalidArgumentException("[{$amount}] is not a decimal amount.");
        }

        $fraction = $match[3] ?? '';
        $kept = str_pad(substr($fraction, 0, $exponent), $exponent, '0');
        $minor = (int) (ltrim($match[2], '0').$kept);

        if (strlen($fraction) > $exponent && (int) $fraction[$exponent] >= 5) {
            $minor++;
        }

        return $match[1] === '-' ? -$minor : $minor;
    }

    public function exponent(string $currency): int
    {
        return $this->rules($currency)->exponent;
    }

    private function rules(string $currency): CurrencyFormat
    {
        $currency = strtoupper($currency);

        return $this->formats[$currency] ??= $this->currencies->format($currency);
    }

    private function group(string $whole, string $separator): string
    {
        $whole = ltrim($whole, '0');

        if ($whole === '') {
            return '0';
        }

        $groups = array_map(strrev(...), array_reverse(str_split(strrev($whole), 3)));

        return implode($separator, $groups);
    }
}
