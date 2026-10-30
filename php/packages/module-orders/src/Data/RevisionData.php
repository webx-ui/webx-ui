<?php

declare(strict_types=1);

namespace WebxUi\Orders\Data;

use Carbon\CarbonImmutable;
use WebxUi\Orders\Models\OrderRevision;

/** An edit of the lines as the events carry it (§38.1, §39): plain values, the row of `order_revisions`. */
final readonly class RevisionData
{
    /**
     * @param  list<array{op: string, item_id: int|null, name: string, options_label: string|null, price: int, qty_from: int, qty_to: int, total_from: int, total_to: int}>  $changes
     * @param  list<array{adjustment_id: int, label: string, amount_from: int, amount_to: int}>  $adjustments
     * @param  array{subtotal: int, adjustments_total: int, total: int, tenders_total: int, payable: int, balance: int}  $before
     * @param  array{subtotal: int, adjustments_total: int, total: int, tenders_total: int, payable: int, balance: int}  $after
     */
    public function __construct(
        public int $id,
        public int $number,
        public array $changes,
        public array $adjustments,
        public array $before,
        public array $after,
        public ?string $reason,
        public ?string $message,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}

    public static function of(OrderRevision $revision): self
    {
        return new self(
            id: $revision->id,
            number: $revision->number,
            changes: $revision->changes,
            adjustments: $revision->adjustments,
            before: $revision->before,
            after: $revision->after,
            reason: $revision->reason,
            message: $revision->message,
            source: $revision->source,
            adminId: $revision->admin_id,
            at: CarbonImmutable::instance($revision->created_at ?? CarbonImmutable::now()),
        );
    }
}
