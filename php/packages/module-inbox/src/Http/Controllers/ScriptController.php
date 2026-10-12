<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Inbox\Rendering\Assets;

/**
 * `{path}/inbox.js` — the captcha's half of sending a form (§10) — and `{path}/inbox-legacy.js`,
 * the script a view published before W6.4 sends itself with.
 *
 * Immutable, because the address carries a hash of the contents: a release that changes a
 * script changes its address, and nothing in between is ever asked for twice.
 */
final class ScriptController
{
    public function __invoke(Request $request): Response
    {
        $route = (string) $request->route()?->getName();
        $route = array_key_exists($route, Assets::SCRIPTS) ? $route : 'webx.inbox.script';

        return new Response(Assets::contents($route), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.Assets::version($route).'"',
        ]);
    }
}
