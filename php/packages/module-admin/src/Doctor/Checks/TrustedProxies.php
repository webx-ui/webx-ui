<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Middleware\TrustProxies;
use ReflectionProperty;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;

/**
 * Whom the site believes the `X-Forwarded-*` headers from.
 *
 * The scheme and the visitor's address come from the proxy in front, but only a proxy the site
 * trusts may say them. Trusting every address (`*`) lets any visitor send `X-Forwarded-Proto` and
 * `X-Forwarded-For` of their own: pass for https, pass for another IP past a limit or a ban, and
 * write it into the logs. The skeleton trusts loopback and the private networks
 * (`config/trustedproxy.php`, `TRUSTED_PROXIES` in `.env`); an older site names its list in
 * `bootstrap/app.php` with `trustProxies(at: ...)`, which wins over the config, and is read here
 * the same way the middleware reads it.
 */
final class TrustedProxies implements Check
{
    private const EVERYONE = ['*', '**', '0.0.0.0/0', '::/0'];

    public function __construct(private readonly Config $config) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $proxies = $this->list($this->fromBootstrap() ?: $this->config->get('trustedproxy.proxies'));

        if ($proxies === []) {
            return [Diagnosis::ok('Trusted proxies', 'none — the X-Forwarded-* headers are ignored. Behind a proxy that ends TLS, links come out http:// until it is named in TRUSTED_PROXIES.')];
        }

        if (array_intersect($proxies, self::EVERYONE) !== []) {
            return [Diagnosis::warn('Trusted proxies', 'every address — any visitor can send X-Forwarded-For and X-Forwarded-Proto and pass for https or for another IP. Name the proxy in TRUSTED_PROXIES (comma-separated); left unset, the skeleton trusts loopback and the private networks.')];
        }

        return [Diagnosis::ok('Trusted proxies', implode(', ', $proxies).'.')];
    }

    /** What `trustProxies(at: ...)` left on the middleware, which the middleware prefers. */
    private function fromBootstrap(): mixed
    {
        try {
            return (new ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies'))->getValue();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    private function list(mixed $proxies): array
    {
        $proxies = is_string($proxies) ? explode(',', $proxies) : (is_array($proxies) ? $proxies : []);

        return array_values(array_filter(
            array_map(static fn (mixed $proxy): string => is_string($proxy) ? trim($proxy) : '', $proxies),
            static fn (string $proxy): bool => $proxy !== '',
        ));
    }
}
