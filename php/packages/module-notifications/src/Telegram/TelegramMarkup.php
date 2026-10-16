<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Telegram;

/**
 * Telegram's HTML, checked when a template is saved rather than refused by Telegram when a
 * submission arrives (§6.1): only its tags, each closed, and no bare `<`.
 *
 * Variables are not markup — their values are escaped when they go in — so `{{name}}` is left out
 * of the check; sections are not, see `problems()`.
 */
final class TelegramMarkup
{
    /** Tag => the attributes it may have. */
    public const TAGS = [
        'b' => [],
        'strong' => [],
        'i' => [],
        'em' => [],
        'u' => [],
        'ins' => [],
        's' => [],
        'strike' => [],
        'del' => [],
        'a' => ['href'],
        'code' => ['class'],
        'pre' => [],
        'blockquote' => ['expandable'],
        'tg-spoiler' => [],
        'span' => ['class'],
    ];

    /**
     * What is wrong, as `[problem, tag]` pairs: `tag` — not Telegram's; `unclosed` — opened and
     * never closed, or closed out of order; `lt` — a `<` that is not a tag (write `&lt;`).
     *
     * A section of the template is a boundary (§7): a tag opened inside `{{#items}}…{{/items}}`
     * closes inside it, or the message breaks whenever the list is empty or has two rows.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function problems(string $template): array
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

            if (! array_key_exists($tag, self::TAGS)) {
                $problems[] = ['tag', $tag];

                continue;
            }

            if ($match[3] === '/') {
                if (end($open) === $tag) {
                    array_pop($open);
                } else {
                    $problems[] = ['unclosed', $tag];
                }

                continue;
            }

            if ($tag === 'span' && preg_match('/class\s*=\s*"tg-spoiler"/', $match[5]) !== 1) {
                // Refused, but still opened: its closing tag is not a second problem.
                $problems[] = ['tag', 'span'];
                $open[] = $tag;

                continue;
            }

            $open[] = $tag;
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

    /** The text Telegram shows: no tags, entities read back. */
    public static function plain(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
