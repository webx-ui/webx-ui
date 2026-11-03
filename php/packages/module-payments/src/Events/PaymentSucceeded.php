<?php

declare(strict_types=1);

namespace WebxUi\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An attempt became `paid` (spec §9.1): after the commit, beside the order's own `OrderPaid` or
 * `OrderPaymentReceived` — for the satellites and the manager's notices, never the order's fan.
 */
final readonly class PaymentSucceeded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $paymentId,
        public string $uuid,
        public int $orderId,
        public string $methodKey,
        public int $amount,
        public string $currency,
        public ?string $gatewayRef,
    ) {}
}
