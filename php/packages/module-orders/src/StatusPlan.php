<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * What a change of the statuses did or, on a dry run, would do (spec §5.6): the panel and the
 * agent say it before it is done — how many orders stand where it touches, which statuses move to
 * another public group and how many buyers see a new name because of it — and `refusal` says why
 * it cannot be done, as a code of {@see StatusRefused}.
 */
final readonly class StatusPlan
{
    /**
     * @param  int  $orders  the orders standing in the status — or the group — the change is about
     * @param  list<array{id: int, from: int, to: int, orders: int}>  $rebound  statuses that move to another public group, with their orders
     * @param  list<int>  $active  a public group's statuses that are still alive, which keep it from going
     * @param  int|null  $id  the status written
     */
    public function __construct(
        public ?string $refusal = null,
        public int $orders = 0,
        public array $rebound = [],
        public array $active = [],
        public bool $dryRun = false,
        public ?int $id = null,
    ) {}

    public function done(int $id): self
    {
        return new self($this->refusal, $this->orders, $this->rebound, $this->active, false, $id);
    }
}
