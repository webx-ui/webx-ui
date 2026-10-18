<?php

declare(strict_types=1);

namespace WebxUi\Debug\Http\Controllers;

use Illuminate\Http\Response;
use WebxUi\Debug\Bar;

/**
 * `/cms/_debug/bar.{hash}.js` — the script of the debug bar. The same file for everybody, and a
 * visitor never gets a page that names it; under its own hash it is cached for a year.
 */
final class BarController
{
    private const IMMUTABLE = 'public, max-age=31536000, immutable';

    public function __invoke(Bar $bar, string $hash): Response
    {
        return new Response($bar->body(), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => $hash === $bar->hash() ? self::IMMUTABLE : 'no-cache',
        ]);
    }
}
