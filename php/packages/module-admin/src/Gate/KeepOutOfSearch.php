<?php

declare(strict_types=1);

namespace WebxUi\Admin\Gate;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A site with no public part (`webx-admin.public` off) keeps out of search engines, all of it.
 *
 * Global middleware, like {@see CloseSite} and for its reason: an address with no route never
 * reaches a group, and its 404 is a page too. `/robots.txt` is answered here, before any route —
 * the SEO module, when a site has it, would otherwise serve whatever its setting says, and on
 * such a site there is nothing to allow. The header covers what robots.txt cannot: a link to
 * the panel's sign-in somebody posted is still a link a crawler may follow.
 */
final class KeepOutOfSearch
{
    public function __construct(private readonly Config $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->config->get('webx-admin.public', true) !== false) {
            return $next($request);
        }

        if ($request->path() === 'robots.txt') {
            return new HttpResponse("User-agent: *\nDisallow: /\n", 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
