<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 over the Guzzle client `GatewayHttp` builds — its stack, the journal's middleware and
 * Laravel's fakes included — with the base's timeouts, which a PSR-18 call cannot pass itself.
 *
 * @internal
 */
final readonly class JournaledPsrClient implements ClientInterface
{
    public function __construct(
        private Client $client,
        private int|float $timeout,
        private int|float $connectTimeout,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->send($request, [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::TIMEOUT => $this->timeout,
            RequestOptions::CONNECT_TIMEOUT => $this->connectTimeout,
            // What Laravel's own stack (the fakes, the recorder) reads off every request.
            'laravel_data' => [],
        ]);
    }
}
