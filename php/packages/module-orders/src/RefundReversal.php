<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * What `reverseRefund()` did or, on a dry run, would do (decision 98, §9.5): what the record gave
 * back and so comes off the order again, what is left to give back after it — in money, of each
 * tender, of each line — and whether the milestone «refunded» comes off. `refusal` says why it
 * cannot be, as a code.
 */
final readonly class RefundReversal
{
    /** No such record on this order. */
    public const string FOREIGN = 'foreign';

    /** The record is reversed already. */
    public const string REVERSED = 'reversed';

    /**
     * @param  array<int, int>  $tenders  tender id → amount the record gave back
     * @param  array<int, int>  $items  item id → qty the record returned
     * @param  int  $moneyLeft  `payable − refunded_total` after the reversal
     * @param  array<int, int>  $tendersLeft  tender id → what is left of it after the reversal
     * @param  array<int, int>  $itemsLeft  item id → units left of it after the reversal
     * @param  int  $refundedTotal  `refunded_total` after the reversal
     * @param  Milestone|null  $milestoneRemoved  `refunded`, when the reversal takes it off
     */
    public function __construct(
        public int $refundId,
        public int $amount,
        public array $tenders,
        public array $items,
        public int $moneyLeft,
        public array $tendersLeft,
        public array $itemsLeft,
        public int $refundedTotal,
        public ?Milestone $milestoneRemoved,
        public ?string $refusal = null,
        public bool $dryRun = false,
    ) {}

    public static function refused(int $refundId, string $refusal, bool $dryRun): self
    {
        return new self($refundId, 0, [], [], 0, [], [], 0, null, $refusal, $dryRun);
    }
}
