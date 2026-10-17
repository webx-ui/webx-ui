<?php

declare(strict_types=1);

namespace WebxUi\Debug\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory;
use Throwable;

/**
 * The site knocking on its own door by its public name, the way the audit's crawler does: the
 * real Host and SNI, the connection sent to `webx-debug.mcp.resolve_to` when the site cannot
 * reach itself by name. Redirects are not followed — the report is of the address asked.
 */
final class HttpPageFetcher implements PageFetcher
{
    public function __construct(
        private readonly Factory $http,
        private readonly Config $config,
    ) {}

    public function fetch(string $url, array $headers): array
    {
        $started = microtime(true);
        $options = ['http_errors' => false];
        $host = parse_url($url, PHP_URL_HOST);
        $address = $this->config->get('webx-debug.mcp.resolve_to');

        if (is_string($host) && is_string($address) && trim($address) !== '' && defined('CURLOPT_RESOLVE')) {
            $address = trim($address);
            $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:443:{$address}", "{$host}:80:{$address}"]];
        }

        try {
            $response = $this->http->createPendingRequest()
                ->withHeaders($headers + ['User-Agent' => 'WebxDebug/1.0', 'Accept' => 'text/html'])
                ->timeout(max(1, (int) $this->config->get('webx-debug.mcp.timeout', 30)))
                ->withoutRedirecting()
                ->withOptions($options)
                ->get($url);
        } catch (Throwable $failure) {
            return ['status' => null, 'ms' => (int) round((microtime(true) - $started) * 1000), 'error' => $failure->getMessage()];
        }

        return ['status' => $response->status(), 'ms' => (int) round((microtime(true) - $started) * 1000), 'error' => null];
    }
}
