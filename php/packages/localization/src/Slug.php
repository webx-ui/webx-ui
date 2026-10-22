<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Support\Str;

/**
 * The one way a name becomes a code or an address part: `[a-z0-9-]`, transliterated by the
 * language the name is written in.
 *
 * The language matters: `Str::slug()` without one reads Cyrillic the English way, so «Хіт» comes
 * out `xit`, while Ukrainian gives `khit` and Russian `hit`. And a name of no letters (`−20%`)
 * leaves only its digits, which says nothing as a code — `$prefix` turns it into `label-20`.
 */
final class Slug
{
    /**
     * @param  string|null  $prefix  put before a result with no letter in it, or used alone for an empty one
     */
    public static function make(string $text, string $locale, int $max = 255, ?string $prefix = null): string
    {
        $slug = Str::slug($text, '-', $locale);

        if ($prefix !== null && preg_match('/[a-z]/', $slug) !== 1) {
            $slug = $slug === '' ? $prefix : $prefix.'-'.$slug;
        }

        // A cut in the middle of a word must not leave the `-` an address cannot end with.
        return rtrim(substr($slug, 0, $max), '-');
    }

    /**
     * One slug out of a name written in several languages: the site's default language first,
     * then the rest in the order given; the first that gives anything wins.
     *
     * @param  array<array-key, mixed>  $texts  language → name
     */
    public static function first(array $texts, string $default, int $max = 255, ?string $prefix = null): string
    {
        $ordered = array_key_exists($default, $texts) ? [$default => $texts[$default]] + $texts : $texts;

        foreach ($ordered as $locale => $text) {
            if (is_string($text) && trim($text) !== '' && Str::slug($text, '-', (string) $locale) !== '') {
                return self::make($text, (string) $locale, $max, $prefix);
            }
        }

        return $prefix ?? '';
    }
}
