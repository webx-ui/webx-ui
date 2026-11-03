<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use WebxUi\Localization\Locales;

/**
 * A text the shop wrote in the languages of the site (`{locale: text}`), read in one of them.
 */
final class Words
{
    /**
     * The text in the language asked for, else along the site's fallback chain; null — none written.
     *
     * @param  array<array-key, mixed>|null  $words
     */
    public static function pick(?array $words, ?string $locale = null): ?string
    {
        $words = self::clean($words);

        if ($words === []) {
            return null;
        }

        foreach (app(Locales::class)->chain($locale) as $code) {
            if (isset($words[$code])) {
                return $words[$code];
            }
        }

        return null;
    }

    /**
     * Only the non-empty strings, keyed by language.
     *
     * @param  array<array-key, mixed>|null  $words
     * @return array<string, string>
     */
    public static function clean(?array $words): array
    {
        $clean = [];

        foreach ($words ?? [] as $code => $text) {
            if (is_string($code) && is_string($text) && trim($text) !== '') {
                $clean[$code] = $text;
            }
        }

        return $clean;
    }

    /**
     * The text in every language of the site — the shop's own where written, the dictionary's
     * otherwise: what an order copies, so that a later edit does not change it (§10.4).
     *
     * @param  array<array-key, mixed>|null  $words
     * @param  array<string, scalar>  $replace
     * @return array<string, string>
     */
    public static function everywhere(?array $words, string $fallbackKey, array $replace = []): array
    {
        $own = self::clean($words);
        $all = [];

        foreach (app(Locales::class)->codes() as $code) {
            $all[$code] = $own[$code] ?? (string) trans($fallbackKey, $replace, $code);
        }

        return $all;
    }
}
