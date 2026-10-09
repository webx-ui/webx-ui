<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WebxUi\Seo\Http\Probes;
use WebxUi\Seo\Normalisation;

/**
 * One address per page: the main mirror, https, collapsed slashes, no index file, one policy
 * for the trailing slash and lower case — every difference at once, in **one** 301 rather than a
 * chain of them (audit spec §7). Each part is a setting of the SEO tab, off until it is turned
 * on, by hand or by the audit's fix for the finding it closes.
 *
 * Global middleware, before the redirects table, for the reason the redirects are global: the
 * address to normalise may have no route (`/About`, `/blog//post`), and the router throws before
 * a group is entered. Before the table, so a redirect is matched against the address it was
 * written for — the normalised one.
 *
 * What it cannot do: answer for a host or a scheme the web server never hands to Laravel. That
 * part of the mirror and of https lives in nginx or Apache, and the audit's finding says so.
 */
final class NormaliseAddress
{
    private const INDEX = '~/index\.(?:php|html?)$~i';

    /** The front controller written into the middle of an address: `/index.php/about`. */
    private const FRONT = '~^/index\.php(?=/)~i';

    public function __construct(
        private readonly Normalisation $settings,
        private readonly Config $config,
        private readonly Router $router,
        private readonly Probes $probes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // A health probe gets the page it asked for: a 301 to an https nobody serves inside the
        // container is a failed check, and a failed check is a site taken out of routing.
        if (! $request->isMethodCacheable() || $this->probes->is($request)) {
            return $next($request);
        }

        $target = $this->target($request);

        return $target === null ? $next($request) : new RedirectResponse($target, 301);
    }

    /** Where the request should have gone, or null when it went to the right place. */
    public function target(Request $request): ?string
    {
        $uri = $request->getRequestUri();
        $query = '';

        if (($at = strpos($uri, '?')) !== false) {
            $query = substr($uri, $at);
            $uri = substr($uri, 0, $at);
        }

        $base = $request->getBaseUrl();
        // The base is where the front controller lives (`/index.php`, or a subdirectory); the
        // index file it names is exactly what the index part is about, so it stays in the path
        // and is cut there, by the setting.
        $prefix = preg_match(self::INDEX, $base) === 1 ? (string) preg_replace(self::INDEX, '', $base) : $base;
        $path = $prefix !== '' && str_starts_with($uri, $prefix) ? substr($uri, strlen($prefix)) : $uri;
        $path = $path === '' ? '/' : $path;

        // The panel keeps its own path, but not another host or plain http: a panel answering on
        // the www mirror is a second session cookie and a second place to be signed in.
        $panel = $this->isPanel($path);

        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();

        if ($this->settings->https() && $scheme === 'http') {
            $scheme = 'https';
            $port = null;
        }

        $host = $this->mirror($host);
        if ($prefix !== '' && $uri === $prefix) {
            // The root of a site in a subdirectory is `/sub`, and that is left as it is written.
            $path = '';
        } elseif (! $panel) {
            $path = $this->path($path, $request);
        }

        if ([$scheme, $host, $prefix.$path] === [$request->getScheme(), $request->getHost(), $uri]) {
            return null;
        }

        $standard = $port === null || ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);

        return $scheme.'://'.$host.($standard ? '' : ':'.$port).$prefix.$path.$query;
    }

    private function mirror(string $host): string
    {
        $policy = $this->settings->host();

        // An IP or a single word has no mirror: `localhost` is not `www.localhost`.
        if ($policy === '' || filter_var($host, FILTER_VALIDATE_IP) !== false || ! str_contains($host, '.')) {
            return $host;
        }

        $bare = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return $policy === Normalisation::WWW ? 'www.'.$bare : $bare;
    }

    /**
     * The index file by its setting; the rest — slashes, case, the slash at the end — is the
     * registry's spelling, and only for an address the registry answers. A route of the
     * application keeps the path it was declared with: `/oauth/authorize` is not this tab's to
     * spell, and the resolver would otherwise send its own 301 right after this one.
     */
    private function path(string $path, Request $request): string
    {
        if ($this->settings->slashes()) {
            $path = (string) preg_replace('~/{2,}~', '/', $path);
        }

        if ($this->settings->index()) {
            // `/index.php/about` is `/about` with the front controller named: a second address
            // for every page, and the one the canonical used to repeat.
            $path = (string) preg_replace(self::FRONT, '', $path);
            $path = (string) preg_replace(self::INDEX, '/', $path);
        }

        $spelled = $this->settings->path($path);

        return $spelled !== $path && $this->registryAnswers($request) ? $spelled : $path;
    }

    /** Whether the request ends at the registry's fallback rather than at a route of its own. */
    private function registryAnswers(Request $request): bool
    {
        try {
            return $this->router->getRoutes()->match($request)->isFallback;
        } catch (Throwable) {
            return false;
        }
    }

    /** The panel and its API keep their own addresses, as with the redirects. */
    private function isPanel(string $path): bool
    {
        $lower = strtolower($path);

        foreach (['webx-admin.path', 'webx-admin.api_path'] as $key) {
            $panel = strtolower(trim((string) $this->config->get($key, ''), '/'));

            if ($panel !== '' && ($lower === "/{$panel}" || str_starts_with($lower, "/{$panel}/"))) {
                return true;
            }
        }

        return false;
    }
}
