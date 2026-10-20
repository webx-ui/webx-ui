<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Email;

use WebxUi\Notifications\Templates\Html;

/**
 * A letter's template in HTML (§6.2): the tags and attributes a letter is laid out with — tables
 * and inline styles, the way mail clients read it — checked when the template is saved.
 *
 * A template is HTML when it starts with a tag; anything else is the text a letter has always
 * been, so a channel saved before keeps sending what it sent. No script, no form, no stylesheet,
 * no handler: what is not on the lists is refused with the words that say so.
 */
final class EmailMarkup
{
    public const TAGS = [
        'a', 'b', 'strong', 'i', 'em', 'u', 's', 'small', 'span', 'br', 'hr',
        'p', 'div', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
    ];

    public const ATTRIBUTES = [
        'style', 'href', 'title', 'target', 'rel', 'align', 'valign', 'width', 'height',
        'cellpadding', 'cellspacing', 'border', 'role', 'bgcolor', 'colspan', 'rowspan', 'dir', 'lang',
    ];

    private const VOID = ['br', 'hr'];

    /** Whether the template is HTML: its first character, past whitespace, opens a tag. */
    public static function isHtml(string $template): bool
    {
        return preg_match('/^\s*<[a-zA-Z]/', $template) === 1;
    }

    /**
     * What is wrong, as `[problem, what]` pairs: `tag`, `attribute`, `unclosed`, `lt`.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function problems(string $template): array
    {
        $attributes = [];

        $problems = Html::problems($template, static function (string $tag, ?string $given) use (&$attributes): bool {
            if (! in_array($tag, self::TAGS, true)) {
                return false;
            }

            foreach (self::attributes((string) $given) as $name) {
                if (! in_array($name, self::ATTRIBUTES, true)) {
                    $attributes[$name] = ['attribute', $name];
                }
            }

            return true;
        }, self::VOID);

        return [...$problems, ...array_values($attributes)];
    }

    /**
     * The names of a tag's attributes; a variable or a section among them is not one.
     *
     * @return list<string>
     */
    private static function attributes(string $given): array
    {
        $given = (string) preg_replace('/\{\{.*?\}\}\}?/s', '', $given);
        $given = (string) preg_replace('/=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+)/', '', $given);

        preg_match_all('/[^\s"\'\/=]+/', $given, $names);

        return array_map('strtolower', $names[0]);
    }
}
