<?php

declare(strict_types=1);

namespace WebxUi\Media\Support;

/**
 * The alt of a file in the library: what is on the picture, a string per language.
 *
 * The file's own words, which a template prints wherever the place that uses the picture wrote
 * none of its own — a block's image field with an alt of its own still wins
 * ({@see \WebxUi\Media\Screens\MediaValues::resolve()}). A file without one stays without: an
 * empty alt is what a decorative picture should have.
 *
 * Written in the panel (the file's «Alt text») and by an agent (`media_set_alt`); both go
 * through {@see self::clean()}, so the column holds a map of non-empty strings or null.
 */
final class MediaAlt
{
    /** Longer than any alt worth reading aloud, short enough to stop a pasted article. */
    public const MAX = 1000;

    /**
     * A map of languages with the empty ones dropped, or null when nothing is left.
     *
     * A plain string is the alt in one language — `$locale`, the site's default when the caller
     * knows it — for an agent that has no reason to send a map for a site with one language.
     *
     * @return array<string, string>|null
     */
    public static function clean(mixed $value, ?string $locale = null): ?array
    {
        if (is_string($value)) {
            $value = $locale === null ? [] : [$locale => $value];
        }

        if (! is_array($value)) {
            return null;
        }

        $clean = [];

        foreach ($value as $language => $words) {
            if (! is_string($language) || $language === '' || ! is_string($words)) {
                continue;
            }

            $words = trim($words);

            if ($words !== '') {
                $clean[$language] = mb_substr($words, 0, self::MAX);
            }
        }

        return $clean === [] ? null : $clean;
    }
}
