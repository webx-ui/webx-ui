<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Nothing, in the `web` group.
 *
 * The panel is a page served from one origin and a session behind it, so every write it makes
 * needs the `XSRF-TOKEN` cookie — and a browser that has just loaded the panel for the first
 * time has no cookie yet, because it has made no request that sets one. Any route in the group
 * would do it; this is that route, named so that it is obvious why it exists.
 *
 * It used to be Sanctum's `/sanctum/csrf-cookie`, which came with the package the panel no
 * longer depends on. The panel owns it now.
 */
final class CsrfCookieController
{
    public function __invoke(): Response
    {
        return new Response('', 204);
    }
}
