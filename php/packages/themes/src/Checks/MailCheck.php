<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

use WebxUi\Themes\Vocabulary;

/**
 * Rule 25 of spec §16.1 in a letter (§7.5): a mail client reads no CSS variable and often no
 * `<style>` either, so a letter takes the values of the tokens from `theme_token()` on the server
 * and writes them into `style="…"`. Two things break that:
 *
 * - `var(` — the client prints nothing where the colour should be, and nothing says so;
 * - a literal colour — in `style="…"`, in a `<style>`, in `bgcolor`/`color` and the other old
 *   attributes mail still honours, or as a string in the PHP of the template
 *   (`theme_token('color-accent', '#2563eb')`): the letter stops repainting with the site.
 *
 * Not a parser, like {@see CssCheck}: Blade comments are skipped, echoes and `@php` are PHP —
 * read for strings that are colours and otherwise blanked, so `style="color: {{ … }}"` is a
 * declaration with an empty value rather than a find.
 */
final class MailCheck
{
    /** The old presentational attributes of HTML that mail clients still paint with. */
    private const string ATTRIBUTES = 'bgcolor|color|text|link|vlink|alink|bordercolor';

    private const string COLOR = '#[0-9a-f]{3,8}|(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\([^)]*\)';

    /**
     * @return list<array{line: int, message: string}> In the order they stand in the view.
     */
    public static function check(string $view): array
    {
        $source = self::blank('/\{\{--.*?--\}\}/s', $view);
        $findings = [];

        preg_match_all('/var\(\s*--[A-Za-z0-9_-]*/i', $source, $variables, PREG_OFFSET_CAPTURE);

        foreach ($variables[0] as [$variable, $at]) {
            $findings[] = [self::lineAt($source, (int) $at), "{$variable}) in a letter — mail clients do not read CSS variables; write theme_token('…') into style=\"…\" (rule 25)"];
        }

        /* PHP: an echo, a raw echo, @php … @endphp, a PHP tag, and the arguments of a directive. */
        $php = '/\{!!.*?!!\}|\{\{.*?\}\}|@php\b.*?@endphp|<\?php.*?\?>|@[a-z]+\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/si';

        preg_match_all($php, $source, $blocks, PREG_OFFSET_CAPTURE);

        foreach ($blocks[0] as [$block, $start]) {
            preg_match_all('/([\'"])\s*('.self::COLOR.')\s*\1/i', $block, $strings, PREG_OFFSET_CAPTURE);

            foreach ($strings[2] as [$color, $at]) {
                $findings[] = [self::lineAt($source, (int) $start + (int) $at), self::literal($color)];
            }
        }

        $markup = self::blank($php, $source);

        // style="…" and <style>…</style>: read as CSS, and only for colours — a letter may name its
        // own fallback font, the reader's mail client has no file of the theme's anyway (§7.5).
        $styles = [];

        if (preg_match_all('/\sstyle\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $markup, $attributes, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) > 0) {
            foreach ($attributes[0] as $index => $attribute) {
                $value = $attributes[1][$index][0] !== null ? $attributes[1][$index] : $attributes[2][$index];
                $styles[] = ['a{'.$value[0].'}', (int) $value[1] - 2];
            }
        }

        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $markup, $sheets, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($sheets[1] as [$sheet, $at]) {
                $styles[] = [$sheet, (int) $at];
            }
        }

        foreach ($styles as [$css, $at]) {
            foreach (CssCheck::check($css, Vocabulary::base()) as $finding) {
                if ($finding->problem === CssProblem::LiteralColor) {
                    $findings[] = [self::lineAt($markup, $at) + $finding->line - 1, self::literal($finding->subject)];
                }
            }
        }

        if (preg_match_all('/\s(?:'.self::ATTRIBUTES.')\s*=\s*["\']?\s*([^"\'\s>]+)/i', $markup, $legacy, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($legacy[1] as [$value, $at]) {
                if (preg_match('/^(?:'.self::COLOR.'|[a-z]+)$/i', $value) === 1) {
                    $findings[] = [self::lineAt($markup, (int) $at), self::literal($value)];
                }
            }
        }

        usort($findings, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(static fn (array $finding): array => ['line' => $finding[0], 'message' => $finding[1]], $findings);
    }

    private static function literal(string $color): string
    {
        return "literal colour {$color} in a letter — it does not repaint with the site; write theme_token('color-…') into style=\"…\" (rule 25)";
    }

    /** Every match replaced by as many spaces, its newlines kept: offsets and lines stay true. */
    private static function blank(string $pattern, string $source): string
    {
        return (string) preg_replace_callback(
            $pattern,
            static fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]),
            $source,
        );
    }

    private static function lineAt(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, max(0, $offset)) + 1;
    }
}
