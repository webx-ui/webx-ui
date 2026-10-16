<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Telegram;

/**
 * Telegram's HTML, checked when a template is saved rather than refused by Telegram when a
 * submission arrives (§6.1): only its tags, each closed, and no bare `<`.
 *
 * Variables are not markup — their values are escaped when they go in — so `{{…}}` is left out
 * of the check.
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
     * @return list<array{0: string, 1: string}>
     */
    public static function problems(string $template): array
    {
        $text = (string) preg_replace('/\{\{.*?\}\}/s', '', $template);
        $problems = [];
        $open = [];

        preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:\s+[^<>]*)?)\s*(\/?)>|</', $text, $found, PREG_SET_ORDER);

        foreach ($found as $match) {
            if (! isset($match[2]) || $match[2] === '') {
                $problems[] = ['lt', '<'];

                continue;
            }

            $tag = strtolower($match[2]);

            if (! array_key_exists($tag, self::TAGS)) {
                $problems[] = ['tag', $tag];

                continue;
            }

            if ($match[1] === '/') {
                if (end($open) === $tag) {
                    array_pop($open);
                } else {
                    $problems[] = ['unclosed', $tag];
                }

                continue;
            }

            if ($tag === 'span' && preg_match('/class\s*=\s*"tg-spoiler"/', $match[3]) !== 1) {
                // Refused, but still opened: its closing tag is not a second problem.
                $problems[] = ['tag', 'span'];
                $open[] = $tag;

                continue;
            }

            $open[] = $tag;
        }

        foreach (array_reverse($open) as $tag) {
            $problems[] = ['unclosed', $tag];
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
