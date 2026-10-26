<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Pricing;

/**
 * A text of the schema document in one language (decision 6): a string is the default language,
 * a map gives the language asked for, then the default one, then any — an option label in a
 * foreign language is better than an empty card.
 */
final class Texts
{
    public static function pick(mixed $text, string $locale, string $default): string
    {
        if (is_string($text)) {
            return $text;
        }

        if (! is_array($text)) {
            return '';
        }

        foreach ([$locale, $default] as $code) {
            if (is_string($text[$code] ?? null) && trim($text[$code]) !== '') {
                return $text[$code];
            }
        }

        foreach ($text as $words) {
            if (is_string($words) && trim($words) !== '') {
                return $words;
            }
        }

        return '';
    }
}
