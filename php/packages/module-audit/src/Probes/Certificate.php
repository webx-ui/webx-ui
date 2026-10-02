<?php

declare(strict_types=1);

namespace WebxUi\Audit\Probes;

/** What the host's TLS certificate says about itself. */
final readonly class Certificate
{
    public function __construct(
        /** Unix time it stops being valid. */
        public int $validTo,
        /** Whether one of its names covers the host. */
        public bool $matchesHost,
        /** Whether the chain verified against the system's roots. */
        public bool $trusted,
        public ?string $issuer = null,
    ) {}

    public function daysLeft(int $now): int
    {
        return (int) floor(($this->validTo - $now) / 86400);
    }

    /**
     * @return array{valid_to: int, matches_host: bool, trusted: bool, issuer: string|null}
     */
    public function toArray(): array
    {
        return [
            'valid_to' => $this->validTo,
            'matches_host' => $this->matchesHost,
            'trusted' => $this->trusted,
            'issuer' => $this->issuer,
        ];
    }
}
