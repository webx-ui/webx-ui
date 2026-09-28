<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Rendering;

use Illuminate\Support\Number;

/**
 * A price as a card prints it (§4.1): `750`, `12.50`, `1,380` or `1 380` by the language.
 *
 * Formatted here rather than in the template, because the template of the offered block is
 * written in the little of Blade the playground's preview also reads (CLAUDE.md §4), and
 * `number_format` is not in it. No fraction when it is zero, two digits otherwise — "12.5 $" reads
 * as a typo. The symbol is not part of it: where it stands is the site's layout (decision 3).
 */
final class Amount
{
    public static function format(?float $price, string $locale): string
    {
        if ($price === null) {
            return '';
        }

        $digits = abs($price - round($price)) < 0.005 ? 0 : 2;

        if (extension_loaded('intl')) {
            $formatted = Number::format($price, precision: $digits, locale: $locale);

            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return number_format($price, $digits, '.', "\u{00A0}");
    }
}
