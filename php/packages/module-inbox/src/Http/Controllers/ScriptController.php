<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Inbox\Rendering\Assets;

/**
 * `{path}/inbox.js` — the one file that turns a working form into a form that answers without
 * reloading the page (§10).
 *
 * Immutable, because its address carries a hash of its contents: a release that changes the
 * script changes the address, and nothing in between is ever asked for twice.
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
