<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Throwable;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Calls;

/**
 * What is said on saving and never refused (§15): selectors outside the block's prefix,
 * bare element selectors, a media query where a container query belongs, no `data-wx-block`
 * on the root. Each is a habit that bites later — on another block, on another page — so
 * each is worth a line under the editor, and none is worth a locked save.
 *
 * The same four checks run live in the editor, in `lint.ts`; this is the copy the server
 * sends back with the saved version, so that an agent writing through MCP hears them too.
 */
final class Lints
{
    /**
     * @return list<array{file: string, code: string, line: int|null, message: string}>
     */
    public static function check(string $slug, string $template, string $styles): array
    {
        $lints = [];

        if (! preg_match('/data-wx-block\s*=/', $template)) {
            $lints[] = self::lint('template', 'no-marker', null);
        }

        $lints = [...$lints, ...self::calls($template)];

        $stray = [];
        $bare = [];
        $prefix = '.b-'.$slug;

        foreach (self::selectors($styles) as [$selector, $line]) {
            if (str_starts_with($selector, $prefix) || str_starts_with($selector, '&')) {
                continue;
            }

            if (preg_match('/^[a-z][a-z0-9-]*/i', $selector)) {
                $bare[] = [$selector, $line];
            } else {
                $stray[] = [$selector, $line];
            }
        }

        if ($stray !== []) {
            $lints[] = self::lint('styles', 'stray-selectors', $stray[0][1], ['slug' => $slug, 'selectors' => self::few($stray)]);
        }

        if ($bare !== []) {
            $lints[] = self::lint('styles', 'bare-selectors', $bare[0][1], ['selectors' => self::few($bare)]);
        }

        if (preg_match('/@media\b/', self::withoutComments($styles), $match, PREG_OFFSET_CAPTURE)) {
            $lints[] = self::lint('styles', 'media-query', self::lineAt($styles, (int) $match[0][1]));
        }

        return $lints;
    }

    /**
     * The tags the template calls other types with (§3.8 of the components spec): a type that
     * does not exist — a typo prints nothing on the site, only a line in the log — and a type
     * that is not a literal, which the graph cannot follow, so publishing what it calls will not
     * check this one. Calling a plain block is not worth a word: that is allowed.
     *
     * @return list<array{file: string, code: string, line: int|null, message: string}>
     */
    private static function calls(string $template): array
    {
        $lints = [];
        $dynamic = Calls::dynamic($template);

        if ($dynamic !== []) {
            $lints[] = self::lint('template', 'dynamic-call', $dynamic[0], [], 'calls');
        }

        $calls = Calls::withLines($template);

        if ($calls === []) {
            return $lints;
        }

        try {
            $known = Block::query()->pluck('slug')->all();
        } catch (Throwable) {
            // No tables to ask: nothing to say about names.
            return $lints;
        }

        $reported = [];

        foreach ($calls as [$slug, $line]) {
            if (! in_array($slug, $known, true) && ! isset($reported[$slug])) {
                $reported[$slug] = true;
                $lints[] = self::lint('template', 'unknown-call', $line, ['type' => $slug], 'calls');
            }
        }

        return $lints;
    }

    /**
     * Every selector of every rule with the line it starts on. Preludes of at-rules are
     * skipped; so are the steps of a `@keyframes` block, which look like element selectors and
     * are not.
     *
     * @return list<array{string, int}>
     */
    private static function selectors(string $styles): array
    {
        $source = self::withoutComments($styles);
        $found = [];

        preg_match_all('/([^{};]+)\{/', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as $match) {
            $prelude = trim($match[1][0]);
            $offset = (int) $match[1][1];

            if ($prelude === '' || str_starts_with($prelude, '@')) {
                continue;
            }

            foreach (explode(',', $prelude) as $selector) {
                $selector = trim($selector);

                if ($selector === '' || $selector === 'from' || $selector === 'to' || str_ends_with($selector, '%')) {
                    continue;
                }

                $found[] = [$selector, self::lineAt($source, $offset + strpos($match[1][0], $selector[0]))];
            }
        }

        return $found;
    }

    /** Comments replaced by spaces of the same length, so offsets and lines stay true. */
    private static function withoutComments(string $styles): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            static fn (array $match): string => preg_replace('/[^\n]/', ' ', $match[0]) ?? '',
            $styles,
        );
    }

    private static function lineAt(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, max(0, $offset)) + 1;
    }

    /**
     * @param  list<array{string, int}>  $selectors
     */
    private static function few(array $selectors): string
    {
        $names = array_values(array_unique(array_map(static fn (array $one): string => $one[0], $selectors)));
        $shown = array_slice($names, 0, 3);

        return implode(', ', $shown).(count($names) > 3 ? ', …' : '');
    }

    /**
     * The words of the call lints live in `calls`, beside the rest of what the server says about
     * components; `checks` is the group the panel keeps a copy of for its own live lints.
     *
     * @param  array<string, string>  $params
     * @return array{file: string, code: string, line: int|null, message: string}
     */
    private static function lint(string $file, string $code, ?int $line, array $params = [], string $group = 'checks'): array
    {
        return [
            'file' => $file,
            'code' => $code,
            'line' => $line,
            'message' => (string) __("webx-blocks::{$group}.{$code}", $params),
        ];
    }
}
