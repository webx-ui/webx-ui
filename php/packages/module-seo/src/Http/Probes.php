<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;
use ReflectionProperty;
use Throwable;

/**
 * Requests that are a machine asking whether the site is alive, which no redirect may answer.
 *
 * A container's health check asks `http://127.0.0.1/up` from inside the container. With https
 * normalisation on it got a 301 to an https nobody listens to inside, failed, and the proxy took
 * the "unhealthy" container out of routing — the whole site, panel included, became the proxy's
 * 404. The proxy already sends visitors to https; the probe is not a visitor.
 *
 * Two ways a request is a probe:
 * - its path is the health route — `health:` in `bootstrap/app.php`, which Laravel also exempts
 *   from maintenance mode and which is read from there, plus `webx-seo.probes` (`/up` by default);
 * - it comes from this machine to this machine by address: a loopback client, a loopback
 *   address for a host, and none of the `X-Forwarded-*` headers a proxy in front would add. A
 *   browser on a developer's machine names the site by its domain, and the audit crawls it so,
 *   so neither stops seeing the redirects it is there to see.
 */
final class Probes
{
    private const LOOPBACK = ['127.0.0.1', '::1'];

    /** Addresses only: `localhost` is also what `artisan serve` and a developer's browser say. */
    private const LOCAL_HOSTS = ['127.0.0.1', '::1', '[::1]'];

    public function __construct(private readonly Config $config) {}

    public function is(Request $request): bool
    {
        return $this->isHealth($request) || $this->isLocal($request);
    }

    public function isHealth(Request $request): bool
    {
        $path = '/'.trim($request->path(), '/');

        foreach ($this->healthPaths() as $health) {
            if (strcasecmp($path, '/'.trim($health, '/')) === 0) {
                return true;
            }
        }

        return false;
    }

    public function isLocal(Request $request): bool
    {
        // The socket's address, not `ip()`: a trusted proxy's header is exactly what must be absent.
        $client = (string) $request->server('REMOTE_ADDR', '');

        if (! in_array($client, self::LOOPBACK, true) || ! in_array($request->getHost(), self::LOCAL_HOSTS, true)) {
            return false;
        }

        foreach (['X-Forwarded-For', 'X-Forwarded-Proto', 'X-Forwarded-Host', 'X-Forwarded-Port', 'Forwarded'] as $header) {
            if ($request->headers->has($header)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function healthPaths(): array
    {
        $paths = array_values(array_filter((array) $this->config->get('webx-seo.probes', ['/up']), 'is_string'));

        try {
            // Where `withRouting(health: ...)` leaves its path; there is no public reader of it.
            $never = (new ReflectionProperty(PreventRequestsDuringMaintenance::class, 'neverPrevent'))->getValue();

            foreach ((array) $never as $path) {
                if (is_string($path) && $path !== '' && ! str_contains($path, '*')) {
                    $paths[] = $path;
                }
            }
        } catch (Throwable) {
            // A framework that keeps it elsewhere still has the configured paths.
        }

        return $paths;
    }
}
