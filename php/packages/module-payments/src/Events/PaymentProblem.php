<?php

declare(strict_types=1);

namespace WebxUi\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Money where it should not be, or a hold that needs a hand (spec §9.4): a second payment, not
 * the sum asked, money for a cancelled order, a hold about to expire, a capture the gateway
 * refused. After the commit; the notice `payments.problem` listens to it (§11.5).
 */
final readonly class PaymentProblem implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $paymentId,
        public string $uuid,
        public int $orderId,
        public string $methodKey,
        /** One of the `SettleResult` problems: duplicate, amount_mismatch, order_cancelled, hold_expiring, capture_failed */
        public string $problem,
        /** The manager's words, as in the order's history. */
        public string $message,
        public int $amount,
        public string $currency,
        public ?string $gatewayRef,
    ) {}
}
