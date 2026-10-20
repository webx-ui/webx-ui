<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Templates;

use Closure;

/**
 * HTML in a message template: the values that go into it, the plain text read out of it, and the
 * check of its tags a save runs — Telegram's few tags and a letter's many are the same walk with
 * other lists (`TelegramMarkup`, `EmailMarkup`).
 *
 * `render.ts` of the npm package does the same in the browser, so the preview of an HTML letter is
 * the letter; `PreviewParityTest` holds the two together.
 */
final class Html
{
    /** Block tags: what starts a paragraph of the plain text, and the end of a row or an item a line. */
    private const PARAGRAPHS = 'p|h[1-6]|table|ul|ol|blockquote|pre';

    private const LINES = 'tr|div';

    /**
     * A value in an HTML message: escaped, the visitor's line breaks kept as `<br>` — a message
     * typed in a textarea is unreadable as one line, and the template's own line breaks are not
     * text in HTML.
     */
    public static function escape(string $value): string
    {
        return str_replace(["\r\n", "\r", "\n"], '<br>', htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8'));
    }

    /**
     * The text of an HTML message, for the plain part of a letter: the template's layout whitespace
     * dropped, a paragraph a blank line, a row a line, the cells of a row "label: value", a link its
     * words and its address.
     */
    public static function plain(string $html): string
    {
        $text = (string) preg_replace('/[ \t\r\n\f]+/', ' ', $html);

        $text = (string) preg_replace_callback('~<a\b[^>]*?\bhref\s*=\s*"([^"]*)"[^>]*>(.*?)</a\s*>~is', static function (array $link): string {
            $href = self::decode($link[1]);
            $words = trim(self::decode(strip_tags($link[2])));

            if ($words === '' || $words === $href) {
                return $href;
            }

            return preg_match('~^(https?:|mailto:)~i', $href) === 1 ? "{$link[2]} ({$link[1]})" : $link[2];
        }, $text);

        $text = (string) preg_replace('~<br\s*/?>~i', "\n", $text);
        $text = (string) preg_replace('~</t[dh]\s*>\s*(?=<t[dh][\s>])~i', ': ', $text);
        $text = (string) preg_replace('~<li\b[^>]*>~i', "\n- ", $text);
        $text = (string) preg_replace('~<hr\b[^>]*>|</?(?:'.self::PARAGRAPHS.')\b[^>]*>~i', "\n\n", $text);
        $text = (string) preg_replace('~<div\b[^>]*>|</(?:'.self::LINES.')\s*>~i', "\n", $text);
        $text = self::decode(strip_tags($text));

        $lines = array_map(static fn (string $line): string => trim((string) preg_replace('/ {2,}/', ' ', $line)), explode("\n", $text));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /**
     * What is wrong with the tags of a template, as `[problem, tag]` pairs: `tag` — not allowed
     * (`$allowed` says, given the tag and its attributes — null for a closing tag); `unclosed` — opened and never closed, or
     * closed out of order; `lt` — a `<` that is not a tag.
     *
     * Variables are values, not markup, so `{{name}}` is left out; a section is a boundary: a tag
     * opened inside `{{#items}}…{{/items}}` closes inside it, or the message breaks whenever the list
     * is empty or has two rows.
     *
     * @param  Closure(string, ?string): bool  $allowed
     * @param  list<string>  $void  tags that have no end: `br`, `hr`
     * @return list<array{0: string, 1: string}>
     */
    public static function problems(string $template, Closure $allowed, array $void = []): array
    {
        $problems = [];
        $open = [];

        preg_match_all('/\{\{\{?\s*([#^\/]?)\s*([^}]*?)\s*\}?\}\}|<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:\s+[^<>]*)?)\s*(\/?)>|</', $template, $found, PREG_SET_ORDER);

        foreach ($found as $match) {
            if (str_starts_with($match[0], '{{')) {
                if ($match[1] === '#' || $match[1] === '^') {
                    $open[] = '{{'.$match[2];
                } elseif ($match[1] === '/') {
                    // Whatever is still open inside the section did not close in it.
                    while ($open !== [] && ! str_starts_with((string) end($open), '{{')) {
                        $problems[] = ['unclosed', (string) array_pop($open)];
                    }

                    array_pop($open);
                }

                continue;
            }

            if (! isset($match[4]) || $match[4] === '') {
                $problems[] = ['lt', '<'];

                continue;
            }

            $tag = strtolower($match[4]);
            $closing = $match[3] === '/';

            if ($closing && ! $allowed($tag, null)) {
                $problems[] = ['tag', $tag];

                if (end($open) === $tag) {
                    array_pop($open);
                }

                continue;
            }

            if (! $closing && ! $allowed($tag, $match[5])) {
                $problems[] = ['tag', $tag];

                // Refused, but still opened: its closing tag is not a second problem.
                if (! in_array($tag, $void, true) && $match[6] !== '/') {
                    $open[] = $tag;
                }

                continue;
            }

            if (in_array($tag, $void, true)) {
                continue;
            }

            if ($closing) {
                $problems = [...$problems, ...self::close($open, $tag)];

                continue;
            }

            if ($match[6] !== '/') {
                $open[] = $tag;
            }
        }

        foreach (array_reverse($open) as $tag) {
            if (! str_starts_with($tag, '{{')) {
                $problems[] = ['unclosed', $tag];
            }
        }

        // Each problem once: ten <div> are one thing to fix.
        $unique = [];

        foreach ($problems as $problem) {
            $unique[$problem[0].':'.$problem[1]] = $problem;
        }

        return array_values($unique);
    }

    /**
     * A closing tag: the one open, or one further down with what is open above it unclosed — one
     * missing `</td>` is one problem, not one for every tag after it. A section is not crossed.
     *
     * @param  list<string>  $open
     * @return list<array{0: string, 1: string}>
     */
    private static function close(array &$open, string $tag): array
    {
        $problems = [];
        $at = count($open) - 1;

        while ($at >= 0 && $open[$at] !== $tag && ! str_starts_with($open[$at], '{{')) {
            $at--;
        }

        if ($at < 0 || $open[$at] !== $tag) {
            return [['unclosed', $tag]];
        }

        while (count($open) - 1 > $at) {
            $problems[] = ['unclosed', (string) array_pop($open)];
        }

        array_pop($open);

        return $problems;
    }

    private static function decode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
