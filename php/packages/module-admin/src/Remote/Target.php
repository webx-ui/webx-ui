<?php

declare(strict_types=1);

namespace WebxUi\Admin\Remote;

/**
 * An address that passed the check: the host, the port, and the IP the connection must go to —
 * null for a host the site trusts, which is connected to the usual way.
 */
final class Target
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly ?string $address,
    ) {}

    /**
     * Guzzle options that pin the connection to the checked address, so that a second DNS answer
     * (rebinding) is never asked for. Merge them into a client of your own.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        // An address typed as an IP is connected to as it is: there is no name to answer twice.
        if ($this->address === null || $this->address === $this->host || ! defined('CURLOPT_RESOLVE')) {
            return [];
        }

        $pinned = str_contains($this->address, ':') ? "[{$this->address}]" : $this->address;

        return ['curl' => [CURLOPT_RESOLVE => ["{$this->host}:{$this->port}:{$pinned}"]]];
    }
}
