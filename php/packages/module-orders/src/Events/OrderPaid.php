<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;

/** The milestone «paid» (§7.2): certificates are issued, the buyer gets the letter. */
final readonly class OrderPaid implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        public ?string $reference,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
