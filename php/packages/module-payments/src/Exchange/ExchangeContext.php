<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

use Illuminate\Support\Str;

/**
 * Who an exchange is for — the gateway, the method, the attempt and its order, who started it —
 * and the trace that ties the events of one action together (spec §20.2).
 */
final readonly class ExchangeContext
{
    public string $trace;

    public function __construct(
        public ?string $gateway = null,
        public ?string $methodKey = null,
        public ?int $paymentId = null,
        public ?int $orderId = null,
        public ?string $source = null,
        ?string $trace = null,
    ) {
        $this->trace = $trace ?? self::newTrace();
    }

    /** A ULID: 26 characters, ordered by time like the events it ties. */
    public static function newTrace(): string
    {
        return (string) Str::ulid();
    }
}
