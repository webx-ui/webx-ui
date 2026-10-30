<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * The refund record is not reversed, and nothing is written (decision 98): it is not this order's,
 * or it is reversed already.
 */
final class RefundReversalRefused extends Refusal
{
    public function __construct(public readonly RefundReversal $result)
    {
        parent::__construct((string) $result->refusal, match ($result->refusal) {
            RefundReversal::REVERSED => "Refund {$result->refundId} is reversed already.",
            default => "Refund {$result->refundId} is not this order's.",
        });
    }
}
