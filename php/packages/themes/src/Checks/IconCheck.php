<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

/**
 * Rule 24 of spec §16.1 in a template of the chain — a view of a layer or the template of one of
 * its block types: an icon is `<x-theme::icon>` from the sprite (§13.1), so a layer above can
 * redraw it by name and every icon follows the colour of the text.
 *
 * - `<x-theme::icon name="…">` with a name no layer has prints an empty `<svg>` on the page —
 *   said here, since the page will not say it;
 * - an inline `<svg>` is an icon no layer above can replace. A name bound with `:name` is not
 *   read: the check does not run the template.
 */
final class IconCheck
{
    /**
     * @param  list<string>  $names  The icons the chain has.
     * @return list<array{line: int, message: string}>
     */
    public static function check(string $template, array $names): array
    {
        $source = self::withoutCode($template);
        $findings = [];

        if (preg_match_all('/<x-theme::icon\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/', $source, $tags, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($tags[1] as [$attributes, $offset]) {
                if (preg_match('/(?<![\w:-])name\s*=\s*(["\'])(.*?)\1/s', $attributes, $name) !== 1 || str_contains($name[2], '{{')) {
                    continue;
                }

                if (! in_array($name[2], $names, true)) {
                    $findings[] = [
                        'line' => self::line($source, $offset),
                        'message' => "icon \"{$name[2]}\" — no layer of the chain has icons/{$name[2]}.svg; the page prints an empty <svg>",
                    ];
                }
            }
        }

        if (preg_match_all('/<svg\b/i', $source, $inline, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($inline[0] as [, $offset]) {
                $findings[] = [
                    'line' => self::line($source, $offset),
                    'message' => 'inline <svg> — put the icon in icons/ of the theme and write <x-theme::icon>; a layer above cannot replace an inline one, nor a preset rely on its colour',
                ];
            }
        }

        usort($findings, static fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        return $findings;
    }

    /**
     * Blade comments and PHP blanked out, their line breaks kept, so the lines still count true:
     * the markup is what is read — an `<svg>` built in a string for a data: URL is a picture.
     */
    private static function withoutCode(string $template): string
    {
        return (string) preg_replace_callback(
            '/\{\{--.*?--\}\}|@php\b(?!\s*\().*?@endphp|<\?php.*?\?>/s',
            static fn (array $code): string => str_repeat("\n", substr_count($code[0], "\n")),
            $template,
        );
    }

    private static function line(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }
}
