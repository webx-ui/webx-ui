<?php

declare(strict_types=1);

namespace WebxUi\Auth\Events;

/**
 * Carries the address that was tried rather than a user: most failures are against addresses
 * nobody owns, and those are the ones worth looking at.
 */
class AdminLoginFailed
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?int $userId = null,
    ) {}
}
