<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use RuntimeException;

/**
 * A notification the base must not act on (spec §6.3, §9.2): the signature does not match, or
 * the body is not one the gateway sends. The event stays in the journal as `rejected`.
 */
final class InvalidNotification extends RuntimeException
{
    public const string SIGNATURE = 'signature';

    public const string MALFORMED = 'malformed';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function signature(string $message = 'The signature does not match'): self
    {
        return new self(self::SIGNATURE, $message);
    }

    public static function malformed(string $message = 'The body is not a notification of the gateway'): self
    {
        return new self(self::MALFORMED, $message);
    }
}
