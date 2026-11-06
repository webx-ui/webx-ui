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
    /** The longest slug a form takes. */
    public const int LENGTH = 190;

    /**
     * @param  string|null  $prefix  put before a result with no letter in it, or used alone for an empty one
     * @param  string  $separator  between words: `-` in an address, `_` in a machine name
     */
    public static function make(string $text, string $locale, int $max = 255, ?string $prefix = null, string $separator = '-'): string
    {
        $slug = Str::slug($text, $separator, $locale);

        if ($prefix !== null && preg_match('/[a-z]/', $slug) !== 1) {
            $slug = $slug === '' ? $prefix : $prefix.$separator.$slug;
        }

        // A cut in the middle of a word must not leave the separator an address cannot end with.
        return rtrim(substr($slug, 0, $max), $separator);
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

    /**
     * A name as the value of a slug field: its slug, or the name itself when nothing of it
     * survives transliteration — so that {@see HasLatinSlug} sees a slug that came to nothing,
     * which it answers with `<type>-<id>`, and not a language with no address at all.
     */
    public static function address(string $text, string $locale, int $max = 255): string
    {
        $slug = self::make($text, $locale, $max);

        return $slug !== '' ? $slug : trim($text);
    }

    /**
     * A slug someone wrote, brought to ASCII: the rule of the platform, whatever door it came in by.
     *
     * Only what is not ASCII is rewritten. An ASCII slug is returned as it came — a wrong shape
     * of it is the form's to refuse in words, not this method's to quietly repair — and `_` stays,
     * because it means something in a slug (`make()` would turn it into a hyphen). An answer of
     * `''` means nothing was left: only hieroglyphs, or only signs.
     */
    public static function latin(string $slug, string $locale, int $max = self::LENGTH): string
    {
        if (self::isLatin($slug)) {
            return preg_match('/[a-zA-Z0-9]/', $slug) === 1 ? $slug : '';
        }

        $parts = array_map(
            // `Str::slug()` keeps a letter of a script it has no table for.
            static fn (string $part): string => trim((string) preg_replace('/[^\x00-\x7F]+/', '', Str::slug($part, '-', $locale)), '-'),
            explode('_', $slug),
        );

        return rtrim(substr(implode('_', array_filter($parts, static fn (string $part): bool => $part !== '')), 0, $max), '-_');
    }

    /** Nothing in it but ASCII: what an address may hold without being percent-encoded. */
    public static function isLatin(string $slug): bool
    {
        return preg_match('/[^\x00-\x7F]/', $slug) !== 1;
    }
}
