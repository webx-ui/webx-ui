<?php

declare(strict_types=1);

namespace WebxUi\Cart\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every answer of the cart is one visitor's, a refusal included: no proxy or browser cache keeps
 * it, and none serves it to the next cookie.
 */
final class KeepPrivate
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->setVary(array_values(array_unique([...$response->getVary(), 'Cookie'])));

        return $response;
    }
}
