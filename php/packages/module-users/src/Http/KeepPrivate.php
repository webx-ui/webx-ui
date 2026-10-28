<?php

declare(strict_types=1);

namespace WebxUi\Users\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every page of the module is one visitor's — a form with their token, a refusal with their
 * address (spec §6.1): no proxy, browser or page cache keeps it, and none serves it to the next.
 */
final class KeepPrivate
{
    /**
     * Set on the request of such a page: what is printed there is the visitor's own, so the
     * banner of §8.3 is the server's to show rather than the script's.
     */
    public const string ATTRIBUTE = 'webx-users.private';

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, true);

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->setVary(array_values(array_unique([...$response->getVary(), 'Cookie'])));

        return $response;
    }
}
