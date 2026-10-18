<?php

declare(strict_types=1);

namespace WebxUi\Admin\Remote;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The one way a module fetches an address somebody typed, and only from the public internet.
 *
 * "Fetch this URL" run by the server is a way into the server's own network: `127.0.0.1`, the
 * site's own name, a database on `10.0.0.5`, the cloud metadata at `169.254.169.254`. So:
 *
 * - only `http` and `https`;
 * - the host is resolved here, every address it resolves to is checked, and the connection is
 *   pinned to the checked one (`CURLOPT_RESOLVE`) — a name that answers a public address to the
 *   check and a private one to the connection (DNS rebinding) never gets the second question;
 * - redirects are followed by hand, `remote.max_redirects` at most, each hop checked the same way;
 * - the body stops at the caller's limit instead of filling the memory or the disk first, and
 *   every hop has a connect timeout and a timeout. The limit counts decoded bytes: curl is not
 *   let to decode (`decode_content` counts the compressed ones, so a gzip bomb would fill the
 *   memory before the check), the body is asked for as `identity`, and one that comes
 *   compressed all the same goes through {@see BoundedDecoder};
 * - a failure is said in plain words, never as the transport's error, which names internal
 *   hosts and ports — and never with the body of the answer.
 *
 * `webx-admin.remote.trusted_hosts` names hosts on the site's own network that may be fetched all
 * the same (a staging bucket, an internal image service): they skip the address check and nothing
 * else. `webx-admin.remote.only_hosts`, when not empty, is the whole list of hosts that may be
 * fetched; `onlyHosts()` narrows it further for one caller. A `*.example.com` entry is any
 * subdomain of `example.com`.
 */
final class RemoteFetcher
{
    /** @var list<string>|null */
    private ?array $only = null;

    /** @var list<string> */
    private array $trusted = [];

    public function __construct(
        private readonly Http $http,
        private HostResolver $resolver,
        private readonly Config $config,
    ) {}

    /**
     * The same fetcher, letting through only these hosts — on top of `remote.only_hosts`, never
     * instead of it. An empty list changes nothing.
     *
     * @param  list<string>  $hosts
     */
    public function onlyHosts(array $hosts): self
    {
        $hosts = self::normalise($hosts);

        if ($hosts === []) {
            return $this;
        }

        $fetcher = clone $this;
        $fetcher->only = $hosts;

        return $fetcher;
    }

    /**
     * The same fetcher, trusting these hosts as well as `remote.trusted_hosts` — a module's own
     * older setting for the same thing.
     *
     * @param  list<string>  $hosts
     */
    public function trusting(array $hosts): self
    {
        $fetcher = clone $this;
        $fetcher->trusted = [...$this->trusted, ...self::normalise($hosts)];

        return $fetcher;
    }

    /** The same fetcher asking another resolver — a module's own, kept for its tests. */
    public function resolvingWith(HostResolver $resolver): self
    {
        $fetcher = clone $this;
        $fetcher->resolver = $resolver;

        return $fetcher;
    }

    /**
     * The body into memory, refused past `$maxBytes`.
     *
     * @param  array<string, string>  $headers
     *
     * @throws FetchRefused
     */
    public function get(string $url, int $maxBytes, int $timeout = 30, array $headers = []): Fetched
    {
        return $this->send('GET', $url, max(1, $maxBytes), $timeout, null, $headers);
    }

    /**
     * The body into the file at `$path`, refused past `$maxBytes` — before a byte of it when the
     * server says how large it is. On a refusal the file is removed.
     *
     * @throws FetchRefused
     */
    public function download(string $url, string $path, int $maxBytes, int $timeout = 600): Fetched
    {
        try {
            return $this->send('GET', $url, max(1, $maxBytes), $timeout, $path, []);
        } catch (FetchRefused $refused) {
            @unlink($path);

            throw $refused;
        }
    }

    /**
     * What the address says about itself, without the body.
     *
     * @throws FetchRefused
     */
    public function head(string $url, int $timeout = 10): Fetched
    {
        return $this->send('HEAD', $url, 1, $timeout, null, []);
    }

    /**
     * The check alone, for a caller with a client of its own (the audit's pool): where the
     * connection has to go. Pin it with `Target::options()`; a redirect is the caller's to check.
     *
     * @throws FetchRefused
     */
    public function check(string $url): Target
    {
        $parts = parse_url($url);
        $parts = is_array($parts) ? $parts : [];
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim(trim((string) ($parts['host'] ?? ''), '[]'), '.'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new FetchRefused('Only http and https addresses are fetched.', FetchRefused::SCHEME);
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        foreach ([self::normalise((array) $this->config->get('webx-admin.remote.only_hosts', [])), $this->only ?? []] as $only) {
            if ($only !== [] && ! self::matches($host, $only)) {
                throw new FetchRefused("The host [{$host}] is not one this site fetches from.", FetchRefused::HOST);
            }
        }

        if (self::matches($host, [...self::normalise((array) $this->config->get('webx-admin.remote.trusted_hosts', [])), ...$this->trusted])) {
            return new Target($host, $port, null);
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new FetchRefused("The host [{$host}] does not resolve.", FetchRefused::UNRESOLVED);
        }

        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                throw new FetchRefused("The address [{$host}] is not on the public internet, and only public addresses are fetched.", FetchRefused::PRIVATE);
            }
        }

        return new Target($host, $port, $addresses[0]);
    }

    /**
     * Whether an address is on the public internet. Loopback, private, link-local (which is
     * where cloud metadata lives), shared, reserved and multicast ranges are not, in either
     * family — nor an IPv6 address that carries one of those IPv4 addresses inside it.
     */
    public static function isPublic(string $address): bool
    {
        $address = strtolower(trim($address, '[]'));

        if (str_contains($address, ':')) {
            $binary = @inet_pton($address);

            if ($binary === false || strlen($binary) !== 16) {
                return false;
            }

            $head = substr($binary, 0, 12);
            $embedded = match (true) {
                // ::ffff:a.b.c.d — what a dual-stack socket makes of an IPv4 address.
                $head === str_repeat("\0", 10)."\xff\xff" => substr($binary, 12),
                // 64:ff9b::a.b.c.d — NAT64.
                $head === "\x00\x64\xff\x9b".str_repeat("\0", 8) => substr($binary, 12),
                // 2002:aabb:ccdd::/48 — 6to4.
                str_starts_with($binary, "\x20\x02") => substr($binary, 2, 4),
                default => null,
            };

            if ($embedded !== null) {
                $v4 = inet_ntop($embedded);

                return $v4 !== false && self::isPublic($v4);
            }

            // ::, ::1 and the deprecated IPv4-compatible ::a.b.c.d.
            if ($head === str_repeat("\0", 12)) {
                return false;
            }
        }

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_GLOBAL_RANGE | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * @param  array<string, string>  $headers
     *
     * @throws FetchRefused
     */
    private function send(string $method, string $url, int $limit, int $timeout, ?string $sink, array $headers): Fetched
    {
        $redirects = max(0, (int) $this->config->get('webx-admin.remote.max_redirects', 3));
        $connectTimeout = max(1, (int) $this->config->get('webx-admin.remote.connect_timeout', 10));

        for ($hop = 0; $hop <= $redirects; $hop++) {
            $target = $this->check($url);
            $tooLarge = false;

            $options = [
                // Refused before a byte of the body when the server says how large it is.
                'on_headers' => static function (ResponseInterface $answer) use ($limit, $method, &$tooLarge): void {
                    if ($method !== 'HEAD' && (int) $answer->getHeaderLine('Content-Length') > $limit) {
                        $tooLarge = true;

                        throw new FetchRefused('too-large', FetchRefused::TOO_LARGE);
                    }
                },
                'progress' => static function (int|float $total, int|float $received) use ($limit, $method, &$tooLarge): void {
                    if ($method !== 'HEAD' && ($total > $limit || $received > $limit)) {
                        $tooLarge = true;

                        throw new FetchRefused('too-large', FetchRefused::TOO_LARGE);
                    }
                },
                // Decoded here, by count (BoundedDecoder), not by curl before anybody counts.
                'decode_content' => false,
                ...$target->options(),
            ];

            if ($sink !== null) {
                $options['sink'] = $sink;
            }

            try {
                $response = $this->http
                    ->withoutRedirecting()
                    ->withHeaders($headers + ['Accept-Encoding' => 'identity'])
                    ->connectTimeout(min($connectTimeout, max(1, $timeout)))
                    ->timeout(max(1, $timeout))
                    ->withOptions($options)
                    ->send($method, $url);
            } catch (Throwable $error) {
                // Guzzle wraps what a callback throws; the flag says what happened all the same.
                throw match (true) {
                    $tooLarge => self::tooLarge(),
                    $error instanceof ConnectionException && str_contains(strtolower($error->getMessage()), 'timed out') => new FetchRefused('The address did not answer in time.', FetchRefused::TIMEOUT),
                    $error instanceof ConnectionException => new FetchRefused('The address could not be reached.', FetchRefused::UNREACHABLE),
                    default => new FetchRefused('The address could not be fetched.', FetchRefused::UNREACHABLE),
                };
            }

            if ($response->redirect()) {
                $location = trim((string) $response->header('Location'));

                if ($location === '') {
                    throw new FetchRefused("The address answered {$response->status()} without saying where to.", FetchRefused::STATUS, $response->status());
                }

                $url = self::follow($url, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new FetchRefused("The address answered {$response->status()}.", FetchRefused::STATUS, $response->status());
            }

            return $this->fetched($response, $method, $url, $limit, $sink);
        }

        throw new FetchRefused('The address redirects too many times.', FetchRefused::REDIRECTS);
    }

    private function fetched(Response $response, string $method, string $url, int $limit, ?string $sink): Fetched
    {
        $type = trim(strtolower(explode(';', (string) $response->header('Content-Type'))[0]));
        $type = $type !== '' ? $type : null;

        if ($method === 'HEAD') {
            return new Fetched('', $type, $url, $response->status(), (int) $response->header('Content-Length'));
        }

        $encoding = $response->header('Content-Encoding');

        if (! BoundedDecoder::supports($encoding)) {
            throw self::undecodable();
        }

        if ($sink !== null) {
            $size = BoundedDecoder::decodeFile($sink, $encoding, $limit) ?? throw self::undecodable();

            return new Fetched('', $type, $url, $response->status(), $size, $sink);
        }

        $body = BoundedDecoder::decode($response->body(), $encoding, $limit) ?? throw self::undecodable();

        return new Fetched($body, $type, $url, $response->status(), strlen($body));
    }

    private static function undecodable(): FetchRefused
    {
        return new FetchRefused('The address answered in an encoding this site does not read.', FetchRefused::UNREACHABLE);
    }

    private static function tooLarge(): FetchRefused
    {
        return new FetchRefused('The file is larger than this site accepts.', FetchRefused::TOO_LARGE);
    }

    /**
     * @param  list<string>  $patterns
     */
    private static function matches(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $host || (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $hosts
     * @return list<string>
     */
    private static function normalise(array $hosts): array
    {
        $normal = [];

        foreach ($hosts as $host) {
            $host = strtolower(trim(trim((string) $host), '[]'));

            if ($host !== '') {
                $normal[] = $host;
            }
        }

        return $normal;
    }

    private static function follow(string $from, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            // Absolute, whatever the scheme: the next hop's check refuses what is not http(s).
            return $location;
        }

        $parts = parse_url($from);
        $scheme = (string) ($parts['scheme'] ?? 'http');

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        $origin = $scheme.'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = (string) ($parts['path'] ?? '/');

        return match (true) {
            str_starts_with($location, '/') => $origin.$location,
            str_starts_with($location, '?') => $origin.$path.$location,
            default => $origin.substr($path, 0, (int) strrpos($path, '/') + 1).$location,
        };
    }
}
