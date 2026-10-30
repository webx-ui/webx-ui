<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * An edit of the order's lines (spec §37.1): what to add, which top-level lines get another qty,
 * which go — against the revision the manager saw, so an edit made meanwhile is not overwritten.
 */
final readonly class ItemsEdit
{
    /**
     * @param  int  $revision  `items_revision` the manager saw; another edit in between — `stale`
     * @param  list<array<string, mixed>>  $add  each {type, ref, qty?, options?} — as CartService::add()
     * @param  array<int, int>  $qty  item id → the new qty, top-level lines only; 0 takes the line out
     * @param  list<int>  $remove  item ids, top-level lines only
     * @param  string|null  $reason  why — required from the panel and MCP, never shown to the buyer
     * @param  string|null  $message  words for the buyer in the letter (§40.2)
     * @param  bool  $notify  tell the buyer: `orders.items_changed`
     */
    public function __construct(
        public int $revision,
        public array $add = [],
        public array $qty = [],
        public array $remove = [],
        public ?string $reason = null,
        public ?string $message = null,
        public bool $notify = true,
        public string $source = 'panel',
    ) {}

    public function isEmpty(): bool
    {
        return $this->add === [] && $this->qty === [] && $this->remove === [];
    }
}
