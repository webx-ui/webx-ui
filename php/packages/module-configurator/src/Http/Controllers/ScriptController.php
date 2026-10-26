<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Configurator\Rendering\Assets;

/**
 * `{path}/configurator.js` — the widget of the site (§12.4), built from the engine's own source.
 *
 * Immutable, because the address carries a hash of the contents: a release that changes the
 * script changes its address, and nothing in between is ever asked for twice.
 */
final class ScriptController
{
    public function __invoke(): Response
    {
        return new Response(Assets::contents(), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.Assets::version().'"',
        ]);
    }
}
