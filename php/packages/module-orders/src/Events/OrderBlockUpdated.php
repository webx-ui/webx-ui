<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;

/** A block was written by its owner (§6.4): the waybill number into `delivery`, a new method into `payment`. */
final readonly class OrderBlockUpdated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public string $block,
        /** @var array<string, mixed> */
        public array $patch,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
