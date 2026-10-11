<?php

declare(strict_types=1);

use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\Tokens;

if (! function_exists('theme_token')) {
    /**
     * `theme_token('color-accent')` — the token's value after the merge (spec §7.3): layers,
     * preset, the owner's edits. For mail and anywhere else a CSS variable does not reach.
     * Null, or the default, when the chain gives the token no value or there is no theme.
     */
    function theme_token(string $name, ?string $default = null): ?string
    {
        return app(Tokens::class)->get($name) ?? $default;
    }
}

if (! function_exists('theme_active')) {
    /**
     * Whether the site stands in a theme (spec §10.2). A module that prints a style of its own
     * inline asks first — `function_exists('theme_active') && theme_active()`, since it does not
     * require the engine: under a theme its base look comes in `modules.css`, and an inline
     * `<style>` later in `<head>` would beat the theme on the same class.
     */
    function theme_active(): bool
    {
        return ! app(ThemeChain::class)->isEmpty();
    }
}
