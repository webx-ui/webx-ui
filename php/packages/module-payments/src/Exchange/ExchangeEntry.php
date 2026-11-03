<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

use Carbon\CarbonImmutable;

/**
 * The raw half of an event as it happened (spec §20.2): who, where, the headers and bodies exactly
 * as sent and received. Written once, before anything is parsed, and never changed after.
 */
final readonly class ExchangeEntry
{
    /**
     * @param  array<array-key, mixed>|null  $requestHeaders  name => value or list of values, as they went
     * @param  array<array-key, mixed>|null  $responseHeaders
     */
    public function __construct(
        public ExchangeKind $kind,
        public string $trace,
        public ?string $gateway = null,
        public ?string $methodKey = null,
        public ?int $paymentId = null,
        public ?int $orderId = null,
        public ?string $source = null,
        public ?string $httpMethod = null,
        public ?string $url = null,
        public ?string $ip = null,
        public ?array $requestHeaders = null,
        public ?string $requestBody = null,
        public ?int $responseStatus = null,
        public ?array $responseHeaders = null,
        public ?string $responseBody = null,
        public ?string $transportError = null,
        public ?CarbonImmutable $startedAt = null,
        public ?int $durationMs = null,
        public ?string $eventKey = null,
    ) {}

    public function direction(): Direction
    {
        return $this->kind->direction();
    }
}
