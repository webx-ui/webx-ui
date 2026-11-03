<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/** What «Test the connection» of a gateway answered (spec §14.1). */
final readonly class ConnectionResult
{
    private function __construct(
        public bool $ok,
        /** Words for the panel; never the credentials, never the raw body. */
        public ?string $message,
    ) {}

    public static function ok(?string $message = null): self
    {
        return new self(true, $message);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}
