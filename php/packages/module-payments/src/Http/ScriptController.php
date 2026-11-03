<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Rendering\Assets;

/**
 * `{script_path}/step.js` — the payment step's script (spec §10.3). Immutable: the address carries
 * a hash of the contents, so a release that changes the script changes its address.
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
