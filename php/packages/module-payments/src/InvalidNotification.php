<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use RuntimeException;

/**
 * A notification the base must not act on (spec §6.3, §9.2): the signature does not match, or
 * the body is not one the gateway sends. The event stays in the journal as `rejected`.
 *
 * A satellite may say which payment the body claims to be about — our uuid, the gateway's id —
 * when it can read that much: the base believes nothing of it and changes no status, but the
 * event is linked to that attempt and its order as a claim, so a manager sees on the order's tab
 * that somebody sent a notification about it with a signature that did not match.
 */
final class InvalidNotification extends RuntimeException
{
    public const string SIGNATURE = 'signature';

    public const string MALFORMED = 'malformed';

    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?string $claimedUuid = null,
        public readonly ?string $claimedGatewayRef = null,
    ) {
        parent::__construct($message);
    }

    public static function signature(string $message = 'The signature does not match', ?string $uuid = null, ?string $gatewayRef = null): self
    {
        return new self(self::SIGNATURE, $message, $uuid, $gatewayRef);
    }

    public static function malformed(string $message = 'The body is not a notification of the gateway'): self
    {
        return new self(self::MALFORMED, $message);
    }

    /** The body named a payment, believed or not. */
    public function claims(): bool
    {
        return $this->claimedUuid !== null || $this->claimedGatewayRef !== null;
    }
}
