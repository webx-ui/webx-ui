<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * What `refund()` recorded or, on a dry run, would record (§9.3): the dialog and the agent show
 * only what is left — in money, of each tender, of each line — never a split of their own, and
 * whether this refund gives the order back whole. `refusal` says why it cannot be, as a code, and
 * `field` which part of the request it is about.
 */
final readonly class RefundResult
{
    /** Nothing to give back: money, every tender and every qty are zero. */
    public const string EMPTY = 'empty';

    /** More money than is left of the payable, or less than none. */
    public const string AMOUNT = 'amount';

    /** More of a tender than is left of it — a cancellation gave it back already — or not this order's. */
    public const string TENDER = 'tender';

    /** More units of a line than are left of it, or not this order's line. */
    public const string QTY = 'qty';

    /**
     * @param  array<int, int>  $tenders  tender id → amount, the ones above zero
     * @param  array<int, int>  $items  item id → qty, the ones above zero
     * @param  int  $moneyLeft  `payable − refunded_total` before this refund
     * @param  array<int, int>  $tendersLeft  tender id → what is left of it before this refund
     * @param  array<int, int>  $itemsLeft  item id → units left of it before this refund
     * @param  bool  $full  it gives back money or a tender, and after it all of them are back (§9.2)
     * @param  Milestone|null  $milestone  `refunded`, when this refund sets it
     * @param  string|null  $field  `amount`, `tenders.<id>`, `items.<id>` — what the refusal is about
     */
    public function __construct(
        public int $amount,
        public array $tenders,
        public array $items,
        public int $moneyLeft,
        public array $tendersLeft,
        public array $itemsLeft,
        public bool $full,
        public ?Milestone $milestone,
        public ?string $refusal = null,
        public ?string $field = null,
        public ?int $refundId = null,
        public bool $dryRun = false,
    ) {}

    /** The same answer, with the row it was written as. */
    public function recorded(int $refundId): self
    {
        return new self(
            $this->amount, $this->tenders, $this->items, $this->moneyLeft, $this->tendersLeft, $this->itemsLeft,
            $this->full, $this->milestone, $this->refusal, $this->field, $refundId, $this->dryRun,
        );
    }
}
