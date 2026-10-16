<?php

declare(strict_types=1);

namespace WebxUi\Notifications;

/** What `Notifications::send()` did: the rows it wrote, by what became of them. */
final readonly class Dispatch
{
    /**
     * @param  list<int>  $queued  rows on their way
     * @param  list<int>  $skipped  rows a channel could not take — switched off, deleted, no address
     * @param  list<int>  $failed  rows that could not even be queued
     */
    public function __construct(
        public array $queued = [],
        public array $skipped = [],
        public array $failed = [],
    ) {}

    /**
     * @return list<int>
     */
    public function deliveries(): array
    {
        return [...$this->queued, ...$this->skipped, ...$this->failed];
    }
}
