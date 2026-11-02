<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

/** What a page did — added to the run's counters (§5 `delivery_sync_runs`). */
final readonly class PageResult
{
    /**
     * @param  array<string, list<string>>  $skipped  why → external ids skipped for it
     */
    public function __construct(
        public int $seen = 0,
        public int $created = 0,
        public int $updated = 0,
        public int $queued = 0,
        public array $skipped = [],
    ) {}
}
