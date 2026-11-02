<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Delivery\Rendering\Assets;

/**
 * `{script_path}/delivery.js` — the delivery step's script (§12.3).
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
