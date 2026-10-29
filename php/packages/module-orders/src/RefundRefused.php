<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * The refund is not recorded, and nothing of it is written (§9.3): it asks for more than is left,
 * or for nothing. `result` is what a dry run would have answered, with what is left.
 */
final class RefundRefused extends Refusal
{
    public function __construct(public readonly RefundResult $result)
    {
        parent::__construct((string) $result->refusal, match ($result->refusal) {
            RefundResult::EMPTY => 'The refund gives nothing back.',
            RefundResult::AMOUNT => "Money left to give back: {$result->moneyLeft}.",
            RefundResult::TENDER => "The tender «{$result->field}» has not that much left to give back.",
            default => "The line «{$result->field}» has not that many units left to return.",
        });
    }
}
