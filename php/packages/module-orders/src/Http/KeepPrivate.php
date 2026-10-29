<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The buyer's page of an order is one person's (spec §13.1, §16): no proxy, browser or page cache
 * keeps it, no search engine lists it, and a link in a letter is not followed by a crawler's
 * guess.
 */
final class KeepPrivate
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
