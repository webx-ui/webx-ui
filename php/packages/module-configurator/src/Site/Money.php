<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Site;

use NumberFormatter;
use ResourceBundle;
use Throwable;
use WebxUi\Configurator\Pricing\Breakdown;

/**
 * A sum in its currency as the page prints it — the server's copy of `formatMoney()` of the npm
 * package, which the widget redraws the price with: `Intl.NumberFormat` with the narrow symbol
 * («25 000 ₴», «₴25,000»). The narrow symbol is ICU's own, read from its data, so the number the
 * server printed and the one the widget draws after the first click look the same.
 *
 * Whole sums have no fraction, others two digits, as everywhere in the module.
 */
final class Money
{
    /** @var array<string, string> narrow symbols, by locale and code */
    private static array $symbols = [];

    public static function format(float $value, string $currency, string $locale): string
    {
        $digits = abs($value - round($value)) < 0.005 ? 0 : 2;

        if (! extension_loaded('intl') || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            return trim(Breakdown::number($value, $locale).' '.$currency);
        }

        try {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);
            $formatter->setSymbol(NumberFormatter::CURRENCY_SYMBOL, self::symbol($currency, $locale));
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);

            $formatted = $formatter->format($value);
        } catch (Throwable) {
            $formatted = false;
        }

        return is_string($formatted) && $formatted !== '' ? $formatted : trim(Breakdown::number($value, $locale).' '.$currency);
    }

    /** ICU's narrow symbol of the currency in the locale — the code itself where it has none. */
    private static function symbol(string $currency, string $locale): string
    {
        return self::$symbols[$locale.'|'.$currency] ??= (static function () use ($currency, $locale): string {
            try {
                $bundle = ResourceBundle::create($locale, 'ICUDATA-curr', true);
                $narrow = $bundle?->get('Currencies%narrow');
                $symbol = $narrow instanceof ResourceBundle ? $narrow->get($currency) : null;
            } catch (Throwable) {
                $symbol = null;
            }

            return is_string($symbol) && $symbol !== '' ? $symbol : $currency;
        })();
    }
}
