<?php

declare(strict_types=1);

namespace WebxUi\Localization\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Localization\Locales;

/**
 * The language prefix in front of a module's own route, held to the same rule as every other
 * address.
 *
 * A module's list page — the feed of the blog, the index of the services — is an ordinary route,
 * not a registry row, so the resolver never sees it; and a route declared as `{webxLocale}/blog`
 * matches `anything/blog`. Two things follow, and both are what the resolver does for everybody
 * else (§8.2 of the routing spec):
 *
 * - a first segment that is not one of this site's languages is a 404, not the English page
 *   under an address nobody chose;
 * - the default language's prefix, on a site that does not use one, is a 301 to the address
 *   without it, rather than a second spelling of the same page sitting in the index.
 *
 * It only stands in front of the prefixed copy of each route: without a prefix there is nothing
 * to check. It lives here rather than in the blog, which had it first, because the second module
 * with a list page needed exactly the same thing.
 */
class OneSpellingPerAddress
{
    /** The route parameter the prefixed routes declare. */
    public const PARAMETER = 'webxLocale';

    public function __construct(
        private readonly Locales $locales,
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $wanted = (string) $request->route(self::PARAMETER);

        if ($wanted === '' || ! $this->locales->has($wanted)) {
            throw new NotFoundHttpException;
        }

        if ($this->spelledWithoutPrefix($wanted)) {
            return $this->withoutPrefix($request, $wanted);
        }

        return $next($request);
    }

    private function spelledWithoutPrefix(string $locale): bool
    {
        return $locale === $this->locales->defaultCode()
            && ! (bool) $this->config->get('webx-localization.prefix_default', false);
    }

    /** The same address with the prefix taken off, query string and all — it is somebody's `?page=2`. */
    private function withoutPrefix(Request $request, string $locale): RedirectResponse
    {
        $path = (string) preg_replace('#^/'.preg_quote($locale, '#').'(?=/|$)#i', '', $request->getPathInfo());
        $query = (string) $request->getQueryString();

        return new RedirectResponse(URL::to($path === '' ? '/' : $path).($query === '' ? '' : '?'.$query), 301);
    }
}
