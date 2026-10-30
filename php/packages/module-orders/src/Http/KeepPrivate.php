<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The buyer's page of an order and the checkout are one person's (spec §13.1, §16, §19): no
 * proxy, browser or page cache keeps them, no search engine lists them, and a link in a letter is
 * not followed by a crawler's guess.
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
        // What it shows depends on who asks: the cookie of the cart, the session of the visitor.
        $response->setVary('Cookie', false);

        return $response;
    }
}
