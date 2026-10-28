<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use Illuminate\Contracts\Config\Repository;
use WebxUi\Cart\Contracts\CurrencySource;

/**
 * The site's currency by default: the cart's own setting, then the catalogue's, then none.
 *
 * The rules of writing an amount are a short table of common currencies rather than `intl`: the
 * string depends on the currency and never on the visitor's language, and `intl` would answer by
 * language. A currency the table does not know gets its code after the number, with the number
 * of digits ISO 4217 gives it. A currencies module makes this configurable by binding its own
 * source.
 */
final class ConfigCurrency implements CurrencySource
{
    private const string NBSP = "\u{00A0}";

    /**
     * Symbol, symbol first, spaced, thousands, decimal, exponent.
     *
     * @var array<string, array{string, bool, bool, string, string, int}>
     */
    private const array KNOWN = [
        'EUR' => ['€', false, true, self::NBSP, ',', 2],
        'USD' => ['$', true, false, ',', '.', 2],
        'GBP' => ['£', true, false, ',', '.', 2],
        'JPY' => ['¥', true, false, ',', '.', 0],
        'CNY' => ['¥', true, false, ',', '.', 2],
        'CHF' => ['CHF', true, true, "'", '.', 2],
        'CAD' => ['CA$', true, false, ',', '.', 2],
        'AUD' => ['A$', true, false, ',', '.', 2],
        'INR' => ['₹', true, false, ',', '.', 2],
        'BRL' => ['R$', true, true, '.', ',', 2],
        'PLN' => ['zł', false, true, self::NBSP, ',', 2],
        'CZK' => ['Kč', false, true, self::NBSP, ',', 2],
        'SEK' => ['kr', false, true, self::NBSP, ',', 2],
        'NOK' => ['kr', false, true, self::NBSP, ',', 2],
        'DKK' => ['kr.', false, true, '.', ',', 2],
        'TRY' => ['₺', true, false, '.', ',', 2],
        'KZT' => ['₸', false, true, self::NBSP, ',', 2],
        'GEL' => ['₾', false, true, self::NBSP, ',', 2],
    ];

    /** ISO 4217 currencies whose minor unit is not 2 digits; every other code has 2. */
    private const array EXPONENTS = [
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0,
        'KRW' => 0, 'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0,
        'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
        'CLF' => 4, 'UYW' => 4,
    ];

    public function __construct(private readonly Repository $config) {}

    public function currency(): ?string
    {
        foreach (['webx-cart.currency', 'webx-catalog.price.currency'] as $key) {
            $value = $this->config->get($key);

            if (is_string($value) && preg_match('/^[A-Za-z]{3}$/', trim($value)) === 1) {
                return strtoupper(trim($value));
            }
        }

        return null;
    }

    public function format(string $currency): CurrencyFormat
    {
        $currency = strtoupper($currency);

        if (isset(self::KNOWN[$currency])) {
            [$symbol, $first, $spaced, $thousands, $decimal, $exponent] = self::KNOWN[$currency];

            return new CurrencyFormat($symbol, $first, $spaced, $thousands, $decimal, $exponent);
        }

        return new CurrencyFormat($currency, false, true, self::NBSP, ',', self::EXPONENTS[$currency] ?? 2);
    }
}
