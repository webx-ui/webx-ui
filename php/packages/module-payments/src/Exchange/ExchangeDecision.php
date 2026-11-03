<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

/**
 * The second half of an event (spec §20.2): what the base made of it. Written once, by
 * `UPDATE … WHERE outcome IS NULL`; an incoming notification adds the answer it gave the gateway.
 */
final readonly class ExchangeDecision
{
    /** @param  array<array-key, mixed>|null  $responseHeaders */
    public function __construct(
        public Outcome $outcome,
        public ?int $paymentId = null,
        public ?int $orderId = null,
        public ?SignatureCheck $signature = null,
        public ?string $reported = null,
        public ?string $statusBefore = null,
        public ?string $statusAfter = null,
        public ?string $message = null,
        public ?string $eventKey = null,
        public ?int $responseStatus = null,
        public ?array $responseHeaders = null,
        public ?string $responseBody = null,
    ) {}
}
