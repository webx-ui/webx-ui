<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

use WebxUi\Themes\Vocabulary;

/**
 * CSS knows only tokens (spec §8): one rule for every stylesheet of the chain — the `src/css` of
 * a theme, the CSS of a module and of the widgets, the styles of a block type.
 *
 *  - no literal colour in a value: `#…`, `rgb()` and the rest of the colour functions, a named
 *    colour — `transparent`, `currentColor` and the CSS-wide keywords are not colours of anybody;
 *  - no literal family in `font-family` / `font`: the generic ones are allowed;
 *  - no `var(--site-…)` the vocabulary does not have — it is silently empty;
 *  - no fallback inside `var(--site-…)`: it is a literal value again, only quieter (a block's own
 *    variable, `var(--team-columns, 3)`, keeps its default);
 *  - no `@media` by width: the container decides.
 *  - no `@media` by `prefers-color-scheme`: the engine prints the dark scheme from tokens.json.
 *
 * `tokens.json` is the one place a literal value is legal, and it is not CSS. Declarations inside
 * `@font-face` and `@property` describe a font or a property rather than paint anything, and are
 * not read. Custom properties are read like any other: `--webx-slider-gap: #fff` is a colour too.
 *
 * Not a parser: a walk over braces and semicolons that knows strings, comments and parentheses —
 * enough for stylesheets written by hand, which is what it reads.
 */
final class CssCheck
{
    /** CSS Color 4 — every named colour a browser paints. */
    private const array NAMED = [
        'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond',
        'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse', 'chocolate', 'coral',
        'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray',
        'darkgreen', 'darkgrey', 'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid',
        'darkred', 'darksalmon', 'darkseagreen', 'darkslateblue', 'darkslategray', 'darkslategrey',
        'darkturquoise', 'darkviolet', 'deeppink', 'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue',
        'firebrick', 'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod',
        'gray', 'green', 'greenyellow', 'grey', 'honeydew', 'hotpink', 'indianred', 'indigo', 'ivory', 'khaki',
        'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan',
        'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon',
        'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue', 'lightyellow',
        'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid',
        'mediumpurple', 'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise',
        'mediumvioletred', 'midnightblue', 'mintcream', 'mistyrose', 'moccasin', 'navajowhite', 'navy',
        'oldlace', 'olive', 'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod', 'palegreen',
        'paleturquoise', 'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue',
        'purple', 'rebeccapurple', 'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown',
        'seagreen', 'seashell', 'sienna', 'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow',
        'springgreen', 'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white',
        'whitesmoke', 'yellow', 'yellowgreen',
    ];

    /** The families that are the reader's own fonts rather than somebody's choice. */
    private const array GENERIC_FONTS = [
        'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif',
        'ui-monospace', 'ui-rounded', 'math', 'emoji', 'fangsong',
    ];

    /** CSS-wide keywords: a value that names no colour and no font. */
    private const array WIDE = ['inherit', 'initial', 'unset', 'revert', 'revert-layer'];

    /** What the `font` shorthand may hold besides the family. */
    private const array FONT_KEYWORDS = [
        'normal', 'italic', 'oblique', 'bold', 'bolder', 'lighter', 'small-caps', 'ultra-condensed',
        'extra-condensed', 'condensed', 'semi-condensed', 'semi-expanded', 'expanded', 'extra-expanded',
        'ultra-expanded', 'xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large', 'xxx-large',
        'smaller', 'larger', 'caption', 'icon', 'menu', 'message-box', 'small-caption', 'status-bar',
    ];

    /**
     * Properties whose values are names, not paint — an animation called `fade` or a grid area
     * called `aside` — where a word that happens to be a colour is not one.
     */
    private const string NOT_PAINT = '/^(?:font|font-[a-z-]+|transition(?:-[a-z-]+)?|animation(?:-[a-z-]+)?|grid(?:-[a-z-]+)?|will-change|content|counter-[a-z-]+|quotes|container(?:-[a-z-]+)?|view-transition-name|list-style-type)$/';

    /**
     * @return list<CssFinding> In the order they stand in the stylesheet.
     */
    public static function check(string $css, Vocabulary $vocabulary): array
    {
        $source = self::withoutComments($css);
        $findings = [];

        foreach (self::statements($source) as [$kind, $text, $offset, $inside]) {
            if ($kind === 'prelude') {
                if (preg_match('/^@media\b[^{]*?\b(?:min-|max-|device-)?width\b/i', $text) === 1) {
                    $findings[] = new CssFinding(CssProblem::MediaWidth, self::lineAt($source, $offset), self::shorten($text));
                }

                // The dark scheme is the engine's to print (§7.6): a theme's own would ignore data-theme.
                if (preg_match('/^@media\b[^{]*?\bprefers-color-scheme\b/i', $text) === 1) {
                    $findings[] = new CssFinding(CssProblem::MediaScheme, self::lineAt($source, $offset), self::shorten($text));
                }

                continue;
            }

            if (preg_match('/^@(?:font-face|property)\b/i', $inside) === 1) {
                continue;
            }

            $colon = strpos($text, ':');
            $property = strtolower(trim(substr($text, 0, (int) $colon)));
            $start = $offset + (int) $colon + 1;
            $value = substr($text, (int) $colon + 1);

            foreach (self::declaration($property, $value, $vocabulary) as [$problem, $at, $subject]) {
                $findings[] = new CssFinding($problem, self::lineAt($source, $start + $at), $subject);
            }
        }

        return $findings;
    }

    /**
     * What one declaration breaks, with the offset of each find inside the value.
     *
     * @return list<array{CssProblem, int, string}>
     */
    private static function declaration(string $property, string $value, Vocabulary $vocabulary): array
    {
        $found = [];

        preg_match_all('/var\(\s*--([A-Za-z0-9_-]+)\s*([,)])/', $value, $references, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($references as $reference) {
            $name = $reference[1][0];

            if (! str_starts_with($name, 'site-')) {
                // A block's or a widget's own variable — `var(--team-columns, 3)`, set inline by a
                // field — is not a token: its fallback is its default, and a colour in it is still
                // found below as a colour.
                continue;
            }

            if (! $vocabulary->has(substr($name, 5))) {
                $found[] = [CssProblem::UnknownToken, (int) $reference[0][1], "--{$name}"];
            }

            if ($reference[2][0] === ',') {
                $found[] = [CssProblem::Fallback, (int) $reference[0][1], "var(--{$name}, …)"];
            }
        }

        // What is left once strings, addresses and the names of variables are out of the way —
        // blanked rather than removed, so the offsets stay true.
        $paint = (string) preg_replace_callback(
            '/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|url\([^)]*\)|--[A-Za-z0-9_-]+/i',
            static fn (array $match): string => str_repeat(' ', strlen($match[0])),
            $value,
        );

        preg_match_all('/(?<![\w&-])#[0-9a-f]{3,8}(?![\w-])|(?<![\w-])(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(/i', $paint, $colors, PREG_OFFSET_CAPTURE);

        foreach ($colors[0] as [$color, $at]) {
            $found[] = [CssProblem::LiteralColor, (int) $at, str_ends_with($color, '(') ? $color.'…)' : $color];
        }

        if (! str_starts_with($property, '--') && preg_match(self::NOT_PAINT, $property) !== 1) {
            preg_match_all('/(?<![\w-])('.implode('|', self::NAMED).')(?![\w-])/i', $paint, $named, PREG_OFFSET_CAPTURE);

            foreach ($named[1] as [$color, $at]) {
                $found[] = [CssProblem::LiteralColor, (int) $at, $color];
            }
        } elseif (str_starts_with($property, '--')) {
            // A custom property is anybody's value; a whole value that is a colour name is a colour.
            if (preg_match('/^\s*('.implode('|', self::NAMED).')\s*(?:!important\s*)?$/i', $paint, $named, PREG_OFFSET_CAPTURE) === 1) {
                $found[] = [CssProblem::LiteralColor, (int) $named[1][1], $named[1][0]];
            }
        }

        if ($property === 'font-family' || $property === 'font') {
            foreach (self::families($property, $value) as [$family, $at]) {
                $found[] = [CssProblem::LiteralFont, $at, $family];
            }
        }

        usort($found, static fn (array $a, array $b): int => $a[1] <=> $b[1]);

        return $found;
    }

    /**
     * The families a `font-family` or a `font` names that are neither generic nor a token.
     *
     * @return list<array{string, int}>
     */
    private static function families(string $property, string $value): array
    {
        $bare = (string) preg_replace_callback(
            '/var\([^()]*(?:\([^()]*\)[^()]*)*\)|!important/i',
            static fn (array $match): string => str_repeat(' ', strlen($match[0])),
            $value,
        );

        $allowed = [...self::GENERIC_FONTS, ...self::WIDE];
        $found = [];

        if ($property === 'font-family') {
            preg_match_all('/[^,]+/', $bare, $items, PREG_OFFSET_CAPTURE);

            foreach ($items[0] as [$item, $at]) {
                $family = trim($item);

                if ($family !== '' && ! in_array(strtolower($family), $allowed, true)) {
                    $found[] = [$family, (int) $at + strpos($item, $family[0])];
                }
            }

            return $found;
        }

        // The shorthand: sizes and numbers are not words; keywords and families share the line,
        // and a family of two words — `Open Sans` — is two words in a row that nobody knows.
        $allowed = [...$allowed, ...self::FONT_KEYWORDS];
        $end = null;

        preg_match_all('/"[^"]*"|\'[^\']*\'|(?<![\w.%#-])[A-Za-z_-][A-Za-z0-9_-]*/', $bare, $words, PREG_OFFSET_CAPTURE);

        foreach ($words[0] as [$word, $at]) {
            $at = (int) $at;
            $quoted = $word[0] === '"' || $word[0] === '\'';

            if (! $quoted && in_array(strtolower($word), $allowed, true)) {
                $end = null;

                continue;
            }

            if (! $quoted && $end !== null && trim(substr($bare, $end, $at - $end)) === '') {
                $found[count($found) - 1][0] .= ' '.$word;
            } else {
                $found[] = [$word, $at];
            }

            $end = $quoted ? null : $at + strlen($word);
        }

        return $found;
    }

    /**
     * Preludes and declarations in the order they stand: a prelude is what precedes a `{`, a
     * declaration what a `;` or a `}` closes inside a block. Each with its offset, and the
     * prelude of the at-rule it stands in (empty outside one).
     *
     * @return list<array{'prelude'|'declaration', string, int, string}>
     */
    private static function statements(string $source): array
    {
        $statements = [];
        $stack = [];
        $start = 0;
        $quote = null;
        $parens = 0;
        $length = strlen($source);

        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === '\'') {
                $quote = $char;
            } elseif ($char === '(') {
                $parens++;
            } elseif ($char === ')') {
                $parens = max(0, $parens - 1);
            } elseif ($parens === 0 && ($char === '{' || $char === ';' || $char === '}')) {
                $raw = substr($source, $start, $i - $start);
                $text = trim($raw);
                $offset = $start + (strlen($raw) - strlen(ltrim($raw)));
                $inside = '';

                foreach (array_reverse($stack) as $prelude) {
                    if (str_starts_with($prelude, '@')) {
                        $inside = $prelude;

                        break;
                    }
                }

                if ($char === '{') {
                    $statements[] = ['prelude', $text, $offset, $inside];
                    $stack[] = $text;
                } elseif ($stack !== [] && $text !== '' && ! str_starts_with($text, '@') && str_contains($text, ':')) {
                    $statements[] = ['declaration', $text, $offset, $inside];
                }

                if ($char === '}') {
                    array_pop($stack);
                }

                $start = $i + 1;
            }
        }

        return $statements;
    }

    /** Comments replaced by spaces of the same length, so offsets and lines stay true. */
    private static function withoutComments(string $css): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            static fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]),
            $css,
        );
    }

    private static function lineAt(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, max(0, min($offset, strlen($source)))) + 1;
    }

    private static function shorten(string $text): string
    {
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return strlen($text) > 60 ? substr($text, 0, 57).'…' : $text;
    }
}
