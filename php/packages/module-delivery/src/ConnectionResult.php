<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/** What «Test the connection» of a carrier answered. */
final readonly class ConnectionResult
{
    private function __construct(
        public bool $ok,
        /** Words for the panel; never the credentials. */
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
