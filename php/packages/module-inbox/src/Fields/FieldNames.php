<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Fields;

use WebxUi\Localization\Locales;
use WebxUi\Localization\Slug;

/**
 * A machine name out of a label, for a field nobody named (§2.6).
 *
 * "Your phone" is `your_phone` and «Ваш телефон» is `vash_telefon`: Latin, lower case, words
 * joined by `_` — what a template variable may be called (`{{field.vash_telefon}}`), which is the
 * main reason anybody reads the name. Transliterated by the language the label is written in
 * ({@see Slug}), the main language first. The panel does the same as the label is typed; this is
 * the same rule for a field that arrives without one, from an agent or a hand-written call.
 *
 * Unique among the names given, with `_2`, `_3` after a taken one: two questions labelled "Phone"
 * are two fields, and the second must not answer for the first.
 */
final class FieldNames
{
    /** As long as the column, less room for a suffix. */
    private const MAX = 60;

    /**
     * @param  mixed  $title  the label, as one line or as a map of languages
     * @param  list<string>  $taken  the names the form already has
     */
    public static function fromTitle(mixed $title, array $taken, ?string $locale = null): string
    {
        [$words, $language] = self::words($title, $locale ?? app(Locales::class)->defaultCode());
        $base = self::slug($words, $language);
        $name = $base;

        for ($suffix = 2; in_array($name, $taken, true); $suffix++) {
            $name = $base.'_'.$suffix;
        }

        return $name;
    }

    private static function slug(string $words, string $locale): string
    {
        $slug = Slug::make($words, $locale, self::MAX, separator: '_');

        if ($slug === '') {
            return 'field';
        }

        // The name has to start with a letter, and "2nd phone" does not.
        return preg_match('/^[a-z]/', $slug) === 1 ? $slug : 'field_'.$slug;
    }

    /**
     * The label in the main language, or the first one written — whichever exists — with the
     * language it is in.
     *
     * @return array{0: string, 1: string}
     */
    private static function words(mixed $title, string $locale): array
    {
        if (is_string($title)) {
            return [$title, $locale];
        }

        if (! is_array($title)) {
            return ['', $locale];
        }

        if (is_string($title[$locale] ?? null) && trim($title[$locale]) !== '') {
            return [$title[$locale], $locale];
        }

        foreach ($title as $language => $line) {
            if (is_string($line) && trim($line) !== '') {
                return [$line, (string) $language];
            }
        }

        return ['', $locale];
    }
}
