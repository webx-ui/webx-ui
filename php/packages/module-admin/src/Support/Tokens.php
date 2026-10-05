<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

/**
 * The design tokens of `@webx-ui/tokens`, for a page the panel serves without its Vue app.
 *
 * A consent screen or a gate is plain Blade: it cannot import the npm package, and the panel's
 * own stylesheet comes out of the site's Vite build, which in development is injected by
 * JavaScript and in production hides behind a hashed name. So the package carries a copy —
 * written by the tokens generator, checked against its build by a test on the npm side — and
 * such a page inlines it, which also spares it a request before it can paint.
 */
final class Tokens
{
    public static function css(): string
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/tokens.css');

        return $css === false ? '' : $css;
    }
}
