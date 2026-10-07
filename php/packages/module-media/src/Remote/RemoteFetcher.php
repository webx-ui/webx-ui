<?php

declare(strict_types=1);

namespace WebxUi\Media\Remote;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Throwable;

/**
 * Fetches a file from an address an agent named, and only from the public internet.
 *
 * "Fetch this URL" run by the server is a way into the server's own network: `127.0.0.1`, the
 * site's own name, a database on `10.0.0.5`, the cloud metadata at `169.254.169.254`. So:
 *
 * - the host is resolved here, every address it resolves to is checked, and the connection is
 *   pinned to the checked one (`CURLOPT_RESOLVE`) — a name that answers a public address to the
 *   check and a private one to the connection (DNS rebinding) never gets the second question;
 * - redirects are followed by hand, a few at most, each hop checked the same way;
 * - the body stops at the library's upload limit instead of filling the disk first;
 * - a failure is said in plain words, never as the transport's error, which names internal
 *   hosts and ports.
 *
 * `webx-media.remote.allow_hosts` lets a site name hosts it trusts on its own network (a staging
 * bucket, an internal image service); those skip the address check and nothing else.
 */
final class RemoteFetcher
{
    private const MAX_REDIRECTS = 3;

    public function __construct(
        private readonly Http $http,
        private readonly HostResolver $resolver,
        private readonly Config $config,
    ) {}

    /**
     * @throws FetchRefused
     */
    public function fetch(string $url): Fetched
    {
        $limit = max(1, (int) $this->config->get('webx-media.upload.max_size', 51200)) * 1024;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $address] = $this->check($url);

            $options = [
                'progress' => static function (int|float $total, int|float $received) use ($limit): void {
                    if ($total > $limit || $received > $limit) {
                        throw new FetchRefused('The file is larger than the library accepts.');
                    }
                },
            ];

            if ($address !== null && defined('CURLOPT_RESOLVE')) {
                $pinned = str_contains($address, ':') ? "[{$address}]" : $address;
                $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned}"]];
            }

            try {
                $response = $this->http
                    ->withoutRedirecting()
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->withOptions($options)
                    ->get($url);
            } catch (FetchRefused $refused) {
                throw $refused;
            } catch (ConnectionException $error) {
                throw new FetchRefused(str_contains(strtolower($error->getMessage()), 'timed out')
                    ? 'The address did not answer in time.'
                    : 'The address could not be reached.');
            } catch (Throwable) {
                throw new FetchRefused('The address could not be fetched.');
            }

            if ($response->redirect()) {
                $location = (string) $response->header('Location');

                if ($location === '') {
                    throw new FetchRefused("The address answered {$response->status()} without saying where to.");
                }

                $url = $this->follow($url, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new FetchRefused("The address answered {$response->status()}.");
            }

            $body = $response->body();

            if (strlen($body) > $limit) {
                throw new FetchRefused('The file is larger than the library accepts.');
            }

            $type = trim(strtolower(explode(';', (string) $response->header('Content-Type'))[0]));

            return new Fetched($body, $type !== '' ? $type : null, $url);
        }

        throw new FetchRefused('The address redirects too many times.');
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
     * @return array{0: string, 1: int, 2: string|null} host, port, the address to connect to (null for a trusted host)
     */
    private function check(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new FetchRefused('Only http and https addresses are fetched.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        /** @var list<string> $trusted */
        $trusted = array_map('strtolower', (array) $this->config->get('webx-media.remote.allow_hosts', []));

        if (in_array($host, $trusted, true)) {
            return [$host, $port, null];
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new FetchRefused("The host [{$host}] does not resolve.");
        }

        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                throw new FetchRefused("The address [{$host}] is not on the public internet, and only public addresses are fetched.");
            }
        }

        return [$host, $port, $addresses[0]];
    }

    private function follow(string $from, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($from);
        $scheme = (string) ($parts['scheme'] ?? 'http');

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        $origin = $scheme.'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = (string) ($parts['path'] ?? '/');

        return $origin.substr($path, 0, (int) strrpos($path, '/') + 1).$location;
    }
}
