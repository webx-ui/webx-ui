<?php

declare(strict_types=1);

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
