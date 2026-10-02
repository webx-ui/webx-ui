<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function __construct(
        private readonly Normalisation $settings,
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodCacheable() || ! $this->settings->any()) {
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
        // index file it names is exactly what the index part is about, so it is cut too.
        $path = $base !== '' && str_starts_with($uri, $base) && preg_match(self::INDEX, $base) !== 1
            ? substr($uri, strlen($base))
            : $uri;
        $prefix = preg_match(self::INDEX, $base) === 1 ? (string) preg_replace(self::INDEX, '', $base) : $base;
        $path = $path === '' ? '/' : $path;

        if ($this->isPanel($path)) {
            return null;
        }

        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();

        if ($this->settings->https() && $scheme === 'http') {
            $scheme = 'https';
            $port = null;
        }

        $host = $this->mirror($host);
        // The root of a site in a subdirectory is `/sub`, and that is left as it is written.
        $path = $prefix !== '' && $uri === $prefix ? '' : $this->path($path);

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

    private function path(string $path): string
    {
        if ($this->settings->slashes()) {
            $path = (string) preg_replace('~/{2,}~', '/', $path);
        }

        if ($this->settings->index()) {
            $path = (string) preg_replace(self::INDEX, '/', $path);
        }

        // A file keeps its case and its name: `/files/Report.PDF` is somebody's upload.
        $file = preg_match('~/[^/]+\.[a-z0-9]{1,5}$~i', $path) === 1;

        if ($this->settings->lowercase() && ! $file) {
            // Only the letters a–z: an encoded `%D0%9F` stays the byte it names.
            $path = (string) preg_replace_callback('~(%[0-9A-Fa-f]{2})|[A-Z]+~', static fn (array $match): string => ($match[1] ?? '') !== '' ? $match[1] : strtolower($match[0]), $path);
        }

        $trailing = $this->settings->trailing();

        if ($path !== '/' && ! $file) {
            if ($trailing === Normalisation::STRIP) {
                $path = rtrim($path, '/');
            } elseif ($trailing === Normalisation::ADD && ! str_ends_with($path, '/')) {
                $path .= '/';
            }
        }

        return $path === '' ? '/' : $path;
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
