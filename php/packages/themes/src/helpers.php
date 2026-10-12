<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\UrlGenerator;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\Tokens;

if (! function_exists('theme_token')) {
    /**
     * `theme_token('color-accent')` — the token's value after the merge (spec §7.3): layers,
     * then the preset the theme names. For mail and anywhere else a CSS variable does not reach, so a
     * value that refers to another token comes with that token's value in its place
     * (`font-heading` → the family of `font-body`). Null, or the default, when the chain gives the
     * token no value or there is no theme.
     */
    function theme_token(string $name, ?string $default = null): ?string
    {
        return app(Tokens::class)->resolved($name) ?? $default;
    }
}

if (! function_exists('theme_asset')) {
    /**
     * `theme_asset('img/wave.svg')` — the URL of `assets/img/wave.svg` of the highest layer of
     * the chain that has it, as `webx:theme:sync` published it (spec §13.2): cached forever, a
     * new path when the file changes. An empty string when no layer has it or sync has not run
     * since — a missing picture on the page rather than a 500; `webx:theme:check` names the
     * first, and in debug the log names both.
     */
    function theme_asset(string $path): string
    {
        $chain = app(ThemeChain::class);
        $assets = app(ThemeAssets::class);
        $url = $assets->asset($chain, $path, app(UrlGenerator::class));

        if ($url === null && config('app.debug')) {
            $source = ThemeAssets::source($chain, $path);

            logger()->warning($source === null
                ? "theme_asset('{$path}'): no layer of the theme has assets/{$path}"
                : "theme_asset('{$path}'): assets/{$path} of {$source['layer']->name} is not published: php artisan webx:theme:sync");
        }

        return $url ?? '';
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
