<?php

declare(strict_types=1);

namespace WebxUi\Themes;

/**
 * What `@webxThemeColor` prints in `<head>` (COMPOSITIONS §7): the colour a phone paints its
 * browser bar with — a token of the chain, `color-bg` unless the node names another, with the
 * references to other tokens resolved and the preset applied.
 *
 *     <meta name="theme-color" content="#ffffff">
 *
 * When the dark scheme (§7.6) gives the token another value, one tag a scheme:
 *
 *     <meta name="theme-color" media="(prefers-color-scheme: light)" content="#ffffff">
 *     <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#111418">
 *
 * Read on the request from the tokens already merged for `@webxTheme`: a new preset or a token
 * edit needs no compile of the compositions. Without a theme, or for a token without a value
 * that resolves, nothing — a page without the tag is what every page had before.
 */
class ThemeColor
{
    public const string TOKEN = 'color-bg';

    public function __construct(
        private readonly ThemeChain $chain,
        private readonly Tokens $tokens,
    ) {}

    public function render(string $token = self::TOKEN): string
    {
        if ($this->chain->isEmpty()) {
            return '';
        }

        $light = $this->tokens->resolved($token);

        if ($light === null || ! $this->tokens->vocabulary()->accepts($token, $light)) {
            return '';
        }

        $dark = $this->dark($token, []);

        if ($dark === null || $dark === $light || ! $this->tokens->vocabulary()->accepts($token, $dark)) {
            return '<meta name="theme-color" content="'.e($light).'">';
        }

        return '<meta name="theme-color" media="(prefers-color-scheme: light)" content="'.e($light).'">'."\n"
            .'<meta name="theme-color" media="(prefers-color-scheme: dark)" content="'.e($dark).'">';
    }

    /**
     * The value in the dark: the token's dark value or its light one, every `var(--site-…)` in it
     * read the same way — a light token that points at a dark-tuned one turns dark with it.
     *
     * @param  array<string, true>  $seen
     */
    private function dark(string $name, array $seen): ?string
    {
        $value = $this->tokens->dark()[$name] ?? $this->tokens->get($name);

        if ($value === null || isset($seen[$name])) {
            return null;
        }

        $seen[$name] = true;
        $broken = false;

        $resolved = (string) preg_replace_callback('/var\(\s*--site-([A-Za-z0-9_-]+)\s*\)/', function (array $reference) use ($seen, &$broken): string {
            $value = $this->dark($reference[1], $seen);
            $broken = $broken || $value === null;

            return $value ?? '';
        }, $value);

        return $broken ? null : $resolved;
    }
}
