<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Mcp;

use WebxUi\Tariffs\Currencies;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Rendering\Amount;

/**
 * A tariff's price in one line, as an agent reads it: "$750 /mo", or the words instead of a number,
 * or '' for neither — the symbol before the number, as the offered block prints it.
 *
 * The same falls back as the card does (decision 12): the period and the words come from the
 * default language when the one asked for has none.
 */
final class PriceLine
{
    public static function of(Tariff $tariff, Currencies $currencies, string $locale, string $default): string
    {
        if ($tariff->price === null) {
            return $tariff->wordsIn('price_text', $locale, $default);
        }

        $period = $tariff->wordsIn('period', $locale, $default);
        $symbol = $currencies->symbol($tariff->currency);
        // "zł 90" and "CHF 750" rather than "zł90": a symbol made of letters needs the space.
        $gap = preg_match('/\p{L}$/u', $symbol) === 1 ? ' ' : '';
        $amount = $symbol.$gap.Amount::format((float) $tariff->price, $locale);

        return $period === '' ? $amount : "{$amount} {$period}";
    }
}
