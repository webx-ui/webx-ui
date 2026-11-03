<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * What a satellite read of a server-to-server notification (spec §6.3): a status the base may
 * apply, a mere reason to ask the gateway (an unsigned notification, decision 33), or something
 * that is not about a payment's status at all.
 */
final readonly class Notification
{
    public const string STATUS = 'status';

    public const string POKE = 'poke';

    public const string UNRELATED = 'unrelated';

    private function __construct(
        public string $kind,
        /** The gateway's id of the event, or sha256 of the raw body: a repeat has the same key. */
        public string $eventKey,
        public ?string $uuid,
        public ?string $gatewayRef,
        public ?GatewayStatus $status = null,
        public ?string $reason = null,
    ) {}

    /** A status the base may apply (a signed notification of a trusted type). */
    public static function status(string $eventKey, ?string $uuid, ?string $gatewayRef, GatewayStatus $status): self
    {
        return new self(self::STATUS, $eventKey, $uuid, $gatewayRef, $status);
    }

    /** Only a reason to ask the gateway (decision 33): which attempt, nothing more is believed. */
    public static function poke(string $eventKey, ?string $uuid, ?string $gatewayRef): self
    {
        return new self(self::POKE, $eventKey, $uuid, $gatewayRef);
    }

    /** Not about an attempt's status: a dispute, a card token, an event type we do not take. */
    public static function unrelated(string $eventKey, string $reason, ?string $uuid = null, ?string $gatewayRef = null): self
    {
        return new self(self::UNRELATED, $eventKey, $uuid, $gatewayRef, null, $reason);
    }

    public function isStatus(): bool
    {
        return $this->kind === self::STATUS;
    }

    public function isPoke(): bool
    {
        return $this->kind === self::POKE;
    }

    public function isUnrelated(): bool
    {
        return $this->kind === self::UNRELATED;
    }

    /** The key of a notification without an id of its own: the raw body's sha256. */
    public static function keyOf(string $rawBody): string
    {
        return hash('sha256', $rawBody);
    }
}
