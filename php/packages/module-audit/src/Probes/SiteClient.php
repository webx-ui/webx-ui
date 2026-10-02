<?php

declare(strict_types=1);

namespace WebxUi\Audit\Probes;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * The site knocking on its own door by its public name (decision 6).
 *
 * The request carries the real Host and SNI; the connection goes to `resolve_to` when the
 * setting names one (`CURLOPT_RESOLVE`), so an audit inside Docker or behind NAT reaches the site
 * without hairpinning and still sees what a visitor sees. Redirects are never followed: a chain
 * is a list of answers, and the checks read every step.
 */
final class SiteClient
{
    /** The most of a body kept in memory — a page is read for its links, not stored. */
    private const BODY_LIMIT = 2 * 1024 * 1024;

    private ?string $resolveTo = null;

    public function __construct(
        private readonly Factory $http,
        private readonly Config $config,
    ) {}

    /** The same client, connecting to `$address` for every host it is asked about. */
    public function resolvingTo(?string $address): self
    {
        $client = clone $this;
        $client->resolveTo = $address === null || trim($address) === '' ? null : trim($address);

        return $client;
    }

    public function resolveTo(): ?string
    {
        return $this->resolveTo;
    }

    public function get(string $url): ProbeResponse
    {
        return $this->send('GET', $url);
    }

    /** `HEAD`, and `GET` when the server does not do `HEAD` (§3, stage 5). */
    public function head(string $url): ProbeResponse
    {
        $answer = $this->send('HEAD', $url);

        return in_array($answer->status, [405, 501], true) ? $this->send('GET', $url) : $answer;
    }

    /**
     * Several requests at once — the crawler's pool (decision 7: two at a time by default, so the
     * caller hands over as many as it means to run together).
     *
     * @param  array<array-key, array{0: string, 1: string}>  $requests  key => [method, url]
     * @return array<array-key, ProbeResponse>
     */
    public function many(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $started = microtime(true);

        $responses = $this->http->pool(function (Pool $pool) use ($requests): array {
            $pending = [];

            foreach ($requests as $key => [$method, $url]) {
                $pending[] = $this->configure($pool->as((string) $key), $url)->send($method, $url);
            }

            return $pending;
        });

        $answers = [];

        foreach ($requests as $key => [$method, $url]) {
            $response = $responses[(string) $key] ?? null;

            $answers[(string) $key] = $response instanceof Response
                ? $this->answer($response, $method, $url, $started)
                : new ProbeResponse($url, null, ms: $this->since($started), error: $response instanceof Throwable ? $response->getMessage() : 'No answer.');
        }

        return $answers;
    }

    private function send(string $method, string $url): ProbeResponse
    {
        $started = microtime(true);

        try {
            $response = $this->configure($this->http->createPendingRequest(), $url)->send($method, $url);
        } catch (Throwable $failure) {
            return new ProbeResponse($url, null, ms: $this->since($started), error: $failure->getMessage());
        }

        return $this->answer($response, $method, $url, $started);
    }

    private function configure(PendingRequest $request, string $url): PendingRequest
    {
        return $request
            ->withHeaders(['User-Agent' => (string) $this->config->get('webx-audit.user_agent', 'WebxAudit/1.0')])
            ->timeout(max(1, (int) $this->config->get('webx-audit.timeout', 15)))
            ->withoutRedirecting()
            ->withOptions($this->options($url));
    }

    private function answer(Response $response, string $method, string $url, float $started): ProbeResponse
    {
        $stats = $response->handlerStats();
        $ttfb = $stats['starttransfer_time'] ?? null;
        $total = $stats['total_time'] ?? null;

        return new ProbeResponse(
            $url,
            $response->status(),
            $this->headers($response),
            $method === 'HEAD' ? '' : substr($response->body(), 0, self::BODY_LIMIT),
            is_numeric($total) ? (int) round((float) $total * 1000) : $this->since($started),
            ttfb: is_numeric($ttfb) && (float) $ttfb > 0 ? (int) round((float) $ttfb * 1000) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function options(string $url): array
    {
        // Asked for and decoded by curl; Guzzle then moves the original header to
        // `x-encoded-content-encoding`, which is where the compression check looks too.
        $options = ['decode_content' => 'gzip, deflate', 'http_errors' => false];

        $host = parse_url($url, PHP_URL_HOST);
        $address = $this->address();

        if (is_string($host) && $address !== null && defined('CURLOPT_RESOLVE')) {
            $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:443:{$address}", "{$host}:80:{$address}"]];
        }

        return $options;
    }

    /** The address to connect to: the setting as it is if it is an IP, resolved if a name. */
    public function address(): ?string
    {
        if ($this->resolveTo === null) {
            return null;
        }

        $address = trim($this->resolveTo, '[]');

        if (filter_var($address, FILTER_VALIDATE_IP) !== false) {
            return str_contains($address, ':') ? "[{$address}]" : $address;
        }

        $resolved = gethostbyname($address);

        return $resolved === $address ? null : $resolved;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Response $response): array
    {
        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $headers[strtolower((string) $name)] = (string) ($values[0] ?? '');
        }

        return $headers;
    }

    private function since(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
