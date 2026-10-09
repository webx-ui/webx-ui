<?php

declare(strict_types=1);

namespace WebxUi\Themes;

/**
 * What a token holds, and so which strings may stand for it (spec §7.2).
 *
 * The values end up inside one inline `<style>`, and some of them come from the panel. The
 * check is therefore an allow-list per type rather than a deny-list of dangerous strings: a
 * colour that is not shaped like a colour is refused even when it is harmless, because the
 * harmless and the harmful look alike from here. On top of the shape, every type passes one
 * guard against what could leave the declaration — `<`, `;`, braces, comments, `url()`.
 */
enum TokenType: string
{
    case Color = 'color';
    case Length = 'length';
    case Font = 'font';
    case Shadow = 'shadow';
    case Number = 'number';
    case Time = 'time';
    case Easing = 'easing';

    /** Long enough for a font stack or a two-layer shadow; nothing legitimate needs more. */
    private const int MAX_LENGTH = 200;

    private const string NUMBER = '-?(?:\d+\.?\d*|\.\d+)';

    public function accepts(string $value): bool
    {
        $value = trim($value);

        if (! self::safe($value)) {
            return false;
        }

        // Another site token by reference: `color-accent-contrast` may be `var(--site-color-bg)`.
        if (preg_match('/^var\(--site-[a-z][a-z0-9-]*\)$/', $value) === 1) {
            return true;
        }

        $number = self::NUMBER;

        return match ($this) {
            self::Color => preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) === 1
                || preg_match('/^[a-z]+$/i', $value) === 1
                || preg_match('/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color|color-mix|light-dark)\([a-z0-9#.,%\/\s()+-]*\)$/i', $value) === 1,
            self::Length => $value === '0'
                || preg_match("/^{$number}(?:px|rem|em|%|vw|vh|vmin|vmax|svh|lvh|dvh|ch|ex|lh|cqi|cqw|cqh)$/i", $value) === 1
                || preg_match('/^(?:calc|clamp|min|max)\([a-z0-9.,%\/\s()*+-]*\)$/i', $value) === 1,
            self::Font => preg_match('/^[\p{L}\p{N}\s,\'"_-]+$/u', $value) === 1
                && substr_count($value, '"') % 2 === 0
                && substr_count($value, "'") % 2 === 0,
            self::Shadow => preg_match('/^[a-z0-9#.,%\/\s()+-]+$/i', $value) === 1,
            self::Number => preg_match("/^{$number}$/", $value) === 1,
            self::Time => preg_match('/^(?:\d+\.?\d*|\.\d+)(?:ms|s)$/i', $value) === 1,
            self::Easing => preg_match('/^(?:linear|ease|ease-in|ease-out|ease-in-out|step-start|step-end)$/', $value) === 1
                || preg_match('/^(?:cubic-bezier|steps|linear)\([a-z0-9.,%\s-]*\)$/i', $value) === 1,
        };
    }

    /**
     * What no token of any type may contain. The shapes above already keep these out; this is
     * the second lock, so that loosening one shape later cannot open the `<style>` by accident.
     */
    private static function safe(string $value): bool
    {
        if ($value === '' || strlen($value) > self::MAX_LENGTH || ! mb_check_encoding($value, 'UTF-8')) {
            return false;
        }

        if (preg_match('/[<>{};\\\\@!\x00-\x1F\x7F]|\/\*|url\s*\(/i', $value) === 1) {
            return false;
        }

        $depth = 0;

        foreach (str_split($value) as $char) {
            $depth += match ($char) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }
}
