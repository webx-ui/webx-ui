<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Hosts answered inside the process (spec §21.1): «gateway A» lives at `gateway-a.test` and its
 * API is a PHP object, so that the journal sees its requests and answers as real ones while the
 * site never calls itself over the network. Only `GatewayHttp` asks here: a satellite that went
 * past it to `Http` reaches the real network — and a test's `preventStrayRequests()`.
 */
final class InProcessHosts
{
    /** @var array<string, callable(RequestInterface): ResponseInterface> */
    private array $hosts = [];

    /** @param  callable(RequestInterface): ResponseInterface  $server  may throw a Guzzle ConnectException for a timeout */
    public function serve(string $host, callable $server): void
    {
        $this->hosts[strtolower($host)] = $server;
    }

    public function forget(string $host): void
    {
        unset($this->hosts[strtolower($host)]);
    }

    /** @return (callable(RequestInterface): ResponseInterface)|null */
    public function for(RequestInterface $request): ?callable
    {
        return $this->hosts[strtolower($request->getUri()->getHost())] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->hosts === [];
    }
}
