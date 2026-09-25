<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

/**
 * Which types a template calls by tag — the edges of the graph a version keeps in `uses`.
 *
 * Read from the source rather than from a render: a render only sees the branches its values
 * took, and a card behind `@if ($featured)` is called all the same. Only a literal `type` is an
 * edge. `:type="$x"` is decided at run time, so it cannot be followed, and neither can a literal
 * with an echo in it; both are reported by {@see dynamic()} so the linter can say so.
 */
final class Calls
{
    /**
     * One opening tag, attributes and all. The attributes are matched value by value so that a
     * `>` inside `:card="$a > 1"` does not end the tag early.
     */
    private const TAG = '/<x-webx-block\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)\/?>/';

    /**
     * The slugs of the types a template calls with a literal `type`, sorted, without repeats.
     *
     * @return list<string>
     */
    public static function of(string $template): array
    {
        $slugs = [];

        foreach (self::tags($template) as $attributes) {
            $slug = self::literal($attributes);

            if ($slug !== null && preg_match('/^[a-z][a-z0-9-]*$/', $slug) === 1) {
                $slugs[] = $slug;
            }
        }

        $slugs = array_values(array_unique($slugs));
        sort($slugs);

        return $slugs;
    }

    /**
     * The lines of the tags whose type is not a literal: bound, or echoed into the attribute.
     *
     * @return list<int>
     */
    public static function dynamic(string $template): array
    {
        $lines = [];

        if (preg_match_all(self::TAG, $template, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        foreach ($found as $match) {
            $attributes = $match[1][0];
            $literal = self::literal($attributes);

            if (preg_match('/(?:^|\s):type\s*=/', $attributes) === 1 || ($literal !== null && str_contains($literal, '{{'))) {
                $lines[] = substr_count($template, "\n", 0, (int) $match[0][1]) + 1;
            }
        }

        return $lines;
    }

    /**
     * Every literal call with its line, for a lint that names the line of a type that does not
     * exist.
     *
     * @return list<array{string, int}>
     */
    public static function withLines(string $template): array
    {
        $calls = [];

        if (preg_match_all(self::TAG, $template, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        foreach ($found as $match) {
            $slug = self::literal($match[1][0]);

            if ($slug !== null && preg_match('/^[a-z][a-z0-9-]*$/', $slug) === 1) {
                $calls[] = [$slug, substr_count($template, "\n", 0, (int) $match[0][1]) + 1];
            }
        }

        return $calls;
    }

    /**
     * @return list<string>
     */
    private static function tags(string $template): array
    {
        if (! str_contains($template, '<x-webx-block')) {
            return [];
        }

        preg_match_all(self::TAG, $template, $found);

        return $found[1];
    }

    /** The value of a plain `type` attribute — not `:type`, not `data-type`. */
    private static function literal(string $attributes): ?string
    {
        if (preg_match('/(?:^|\s)type\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/', $attributes, $match) !== 1) {
            return null;
        }

        return trim($match[1] !== '' ? $match[1] : ($match[2] ?? ''));
    }
}
