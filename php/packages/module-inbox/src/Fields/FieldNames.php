<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Fields;

use Illuminate\Support\Str;

/**
 * A machine name out of a label, for a field nobody named (§2.6).
 *
 * "Your phone" is `your_phone` and «Ваш телефон» is `vash_telefon`: Latin, lower case, words
 * joined by `_` — what a template variable may be called (`{{field.vash_telefon}}`), which is the
 * main reason anybody reads the name. The panel does the same as the label is typed; this is the
 * same rule for a field that arrives without one, from an agent or a hand-written call.
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
        $base = self::slug(self::words($title, $locale));
        $name = $base;

        for ($suffix = 2; in_array($name, $taken, true); $suffix++) {
            $name = $base.'_'.$suffix;
        }

        return $name;
    }

    private static function slug(string $words): string
    {
        $slug = Str::slug(Str::transliterate($words), '_');
        $slug = trim(mb_substr($slug, 0, self::MAX), '_');

        if ($slug === '') {
            return 'field';
        }

        // The name has to start with a letter, and "2nd phone" does not.
        return preg_match('/^[a-z]/', $slug) === 1 ? $slug : 'field_'.$slug;
    }

    /** The label in the main language, or the first one written — whichever exists. */
    private static function words(mixed $title, ?string $locale): string
    {
        if (is_string($title)) {
            return $title;
        }

        if (! is_array($title)) {
            return '';
        }

        $locale ??= (string) config('app.fallback_locale');

        if (is_string($title[$locale] ?? null) && trim($title[$locale]) !== '') {
            return $title[$locale];
        }

        foreach ($title as $line) {
            if (is_string($line) && trim($line) !== '') {
                return $line;
            }
        }

        return '';
    }
}
