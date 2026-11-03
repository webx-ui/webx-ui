<?php

declare(strict_types=1);

namespace WebxUi\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An attempt became `failed`, `cancelled` or `expired` (spec §9.1), after the commit. The order
 * hears of it by `OrderPaymentFailed` only when the attempt was its last one.
 */
final readonly class PaymentFailed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $paymentId,
        public string $uuid,
        public int $orderId,
        public string $methodKey,
        /** 'failed' | 'cancelled' | 'expired' */
        public string $status,
        public ?string $reason,
    ) {}
}
