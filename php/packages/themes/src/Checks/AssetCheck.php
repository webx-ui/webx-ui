<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

use WebxUi\Themes\ThemeAssets;

/**
 * The second half of rule 24 of spec §16.1 in a template of the chain — a view of a layer or the
 * template of one of its block types: a picture of the theme is `theme_asset('…')` (§13.2), and
 * the file it names is under `assets/` of some layer. Otherwise the page prints an empty `src`,
 * and nothing on the page says why.
 *
 * Only a literal path is read — `theme_asset('img/' . $name)` is decided when the page runs.
 * Unlike {@see IconCheck}, PHP is read too: `@php $wave = theme_asset('img/wave.svg') @endphp`
 * asks for the file as surely as `{{ }}` does. Blade comments are not.
 */
final class AssetCheck
{
    /**
     * @param  callable(string): bool  $exists  Whether a layer of the chain has `assets/<path>`.
     * @return list<array{line: int, message: string}>
     */
    public static function check(string $template, callable $exists): array
    {
        $findings = [];

        foreach (self::calls($template) as ['line' => $line, 'path' => $path]) {
            $normal = ThemeAssets::normalise($path);

            if ($normal === null) {
                $findings[] = ['line' => $line, 'message' => "theme_asset('{$path}') — not a path under assets/ of a layer: no scheme, no '..', no empty segment"];
            } elseif (! $exists($normal)) {
                $findings[] = ['line' => $line, 'message' => "theme_asset('{$path}') — no layer of the chain has assets/{$normal}; the page prints an empty address"];
            }
        }

        return $findings;
    }

    /**
     * Every `theme_asset()` with a literal path, outside Blade comments.
     *
     * @return list<array{line: int, path: string}>
     */
    public static function calls(string $template): array
    {
        $source = (string) preg_replace_callback(
            '/\{\{--.*?--\}\}/s',
            static fn (array $comment): string => str_repeat("\n", substr_count($comment[0], "\n")),
            $template,
        );

        $calls = [];

        if (preg_match_all('/(?<![\w$>:])theme_asset\(\s*(?:\'([^\'\\\\]*)\'|"([^"\\\\$]*)")\s*\)/', $source, $matches, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) > 0) {
            foreach ($matches[0] as $index => [, $offset]) {
                $calls[] = [
                    'line' => substr_count($source, "\n", 0, (int) $offset) + 1,
                    'path' => (string) ($matches[1][$index][0] ?? $matches[2][$index][0] ?? ''),
                ];
            }
        }

        return $calls;
    }
}
