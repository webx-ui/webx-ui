<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

use WebxUi\Themes\Testing\ThemeContract;
use WebxUi\Themes\ThemeChain;

/**
 * Text against its background (rule 29): pairs below WCAG AA (4.5:1) in every palette a page of
 * the chain can show — the defaults, each preset over them, and with a dark scheme (§7.6) each
 * of those again with the dark values on top, a token without one keeping its light value as the
 * page does. Only colours written as hex can be measured; a pair in another notation, `light-dark()`
 * among them, is left to the eye, not failed.
 *
 * Read from the layers' files as they are, so it answers for a chain whose tokens the page would
 * refuse; `webx:theme:check` and {@see ThemeContract} both ask it.
 */
final class ContrastCheck
{
    /** Text and its background, by token: body text, quiet text, the accent as text and as a button. */
    public const array PAIRS = [
        ['color-text', 'color-bg'],
        ['color-text', 'color-surface'],
        ['color-text-muted', 'color-bg'],
        ['color-text-muted', 'color-surface'],
        ['color-accent-contrast', 'color-accent'],
        ['color-accent', 'color-bg'],
    ];

    /**
     * @return list<string> A line per pair, led by the palette it fails in.
     */
    public static function failures(ThemeChain $chain): array
    {
        $failures = [];

        foreach (self::palettes($chain) as $palette => $values) {
            foreach (self::PAIRS as [$fore, $back]) {
                $ratio = self::contrast(self::resolve($values, $fore), self::resolve($values, $back));

                if ($ratio !== null && $ratio < 4.5) {
                    $failures[] = sprintf('%s: %s on %s is %.2f:1, below 4.5:1', $palette, $fore, $back, $ratio);
                }
            }
        }

        return $failures;
    }

    /**
     * Every palette of the chain by name: `defaults`, `preset <name>`, and `dark scheme`,
     * `preset <name>, dark scheme` when a layer has one.
     *
     * @return array<string, array<string, string>>
     */
    public static function palettes(ThemeChain $chain): array
    {
        $defaults = [];
        $dark = [];
        $presets = [];

        foreach (array_reverse($chain->layers) as $layer) {
            $file = $layer->tokens();
            $defaults = [...$defaults, ...$file->defaults];
            $dark = [...$dark, ...$file->dark];

            foreach ($file->presets as $name => $preset) {
                $presets[$name] = [...($presets[$name] ?? []), ...$preset['tokens']];
            }
        }

        $palettes = ['defaults' => $defaults];

        foreach ($presets as $name => $tokens) {
            $palettes["preset {$name}"] = [...$defaults, ...$tokens];
        }

        if ($dark !== []) {
            foreach ($palettes as $name => $values) {
                $palettes[$name === 'defaults' ? 'dark scheme' : "{$name}, dark scheme"] = [...$values, ...$dark];
            }
        }

        return $palettes;
    }

    /** @param array<string, string> $values */
    private static function resolve(array $values, string $name): ?string
    {
        $value = $values[$name] ?? null;

        for ($depth = 0; $depth < 10 && $value !== null && preg_match('/^var\(--site-([a-z0-9-]+)\)$/', $value, $match) === 1; $depth++) {
            $value = $values[$match[1]] ?? null;
        }

        return $value;
    }

    private static function contrast(?string $fore, ?string $back): ?float
    {
        $lighter = self::luminance($fore);
        $darker = self::luminance($back);

        if ($lighter === null || $darker === null) {
            return null;
        }

        if ($lighter < $darker) {
            [$lighter, $darker] = [$darker, $lighter];
        }

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(?string $colour): ?float
    {
        if ($colour === null || preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $colour, $match) !== 1) {
            return null;
        }

        $hex = strlen($match[1]) === 3 ? preg_replace('/(.)/', '$1$1', $match[1]) : $match[1];

        $channels = array_map(static function (string $pair): float {
            $channel = hexdec($pair) / 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, str_split((string) $hex, 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
