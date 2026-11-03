<?php

declare(strict_types=1);

namespace WebxUi\Orders\Blocks;

use WebxUi\Orders\Balance;
use WebxUi\Orders\Orders;

/**
 * What {@see Orders::changeBlock()} answers: the block's line before and after, its
 * price before and after, the order's totals before and after and the balance it leaves — «was →
 * will be» of the dialog and of an agent's dry run, from one count.
 */
final readonly class BlockChangeResult
{
    /** Received, cancelled or given back whole: nothing is changed in it any more. */
    public const string STATE = 'state';

    /** The block and its price are the ones the order has. */
    public const string NOTHING = 'nothing';

    /**
     * @param  array{subtotal: int, adjustments_total: int, total: int, tenders_total: int, payable: int, balance: int}  $before
     * @param  array{subtotal: int, adjustments_total: int, total: int, tenders_total: int, payable: int, balance: int}  $after
     * @param  array<string, mixed>  $value  the block as it would be written
     */
    public function __construct(
        public string $block,
        public ?string $summaryFrom,
        public ?string $summaryTo,
        public int $amountFrom,
        public int $amountTo,
        public array $before,
        public array $after,
        public Balance $balance,
        public array $value,
        public ?string $refusal,
        public bool $dryRun,
    ) {}

    public function totalChanges(): bool
    {
        return $this->before['total'] !== $this->after['total'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'block' => $this->block,
            'summary_from' => $this->summaryFrom,
            'summary_to' => $this->summaryTo,
            'amount_from' => $this->amountFrom,
            'amount_to' => $this->amountTo,
            'before' => $this->before,
            'after' => $this->after,
            'balance' => ['kind' => $this->balance->kind(), 'due' => $this->balance->due, 'over' => $this->balance->over],
            'refusal' => $this->refusal,
            'dry_run' => $this->dryRun,
        ];
    }
}
