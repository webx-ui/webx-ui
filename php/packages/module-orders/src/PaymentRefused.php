<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/** The payment is not recorded, and nothing is written (§36.3): the order is not paid yet, or it is cancelled. */
final class PaymentRefused extends Refusal
{
    public function __construct(public readonly PaymentRecord $result)
    {
        parent::__construct((string) $result->refusal, match ($result->refusal) {
            PaymentRecord::CANCELLED => 'The order is cancelled: money that came to it is a refund to make.',
            default => 'The order is not paid yet: its first payment is mark(paid).',
        });
    }
}
