<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Stringable;

/**
 * The accented words of a heading (spec §9.4): what `@webxAccent($heading)` prints.
 *
 * The editor marks the words in the heading itself — `Homes ==by the river==` — rather than in a
 * field of their own: one field per language, so each translation puts the accent where its own
 * word order puts it, and the heading stays one `<h2>` with its words in reading order. The marked
 * words print as `<span class="site-accent">`, which the theme colours; a heading without marks
 * prints exactly what `{{ $heading }}` printed.
 *
 * A mark is two equals signs with a word against each of them: `a == b == c` is not one, nor is a
 * pair that never closes — both print as typed. What a heading may already hold stays as it was:
 * the text is escaped, and a heading with shortcodes (`Htmlable`, resolved and escaped already)
 * is marked in that HTML without being escaped a second time.
 */
final class ThemeAccent
{
    /** The class of the accented words: the theme's to colour. */
    public const string CLASS_NAME = 'site-accent';

    /** `==words==`: no space just inside either mark, the shortest pair. */
    private const string PATTERN = '/==(?=\S)(.+?)(?<=\S)==/su';

    /** The heading as HTML, its marked words in a span; nothing for an empty value. */
    public static function heading(mixed $text): HtmlString
    {
        $html = match (true) {
            $text instanceof Htmlable => $text->toHtml(),
            is_string($text), $text instanceof Stringable => e((string) $text),
            is_int($text), is_float($text) => (string) $text,
            default => '',
        };

        return new HtmlString(self::mark($html));
    }

    /** HTML with every pair of marks turned into the span. */
    public static function mark(string $html): string
    {
        if (! str_contains($html, '==')) {
            return $html;
        }

        return preg_replace(self::PATTERN, '<span class="'.self::CLASS_NAME.'">$1</span>', $html) ?? $html;
    }

    /** The text without its marks, for where a span cannot go: an attribute, a title, a summary. */
    public static function plain(string|Stringable|null $text): string
    {
        $text = (string) $text;

        return str_contains($text, '==') ? (preg_replace(self::PATTERN, '$1', $text) ?? $text) : $text;
    }
}
