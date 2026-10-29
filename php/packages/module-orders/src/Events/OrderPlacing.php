<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Orders\Models\Order;

/**
 * The order is written, with its id and number, and not committed yet (§6.3). A listener does
 * here what must happen together with the order or not at all — the wallet spends the tenders,
 * the stock reserves — and refuses with `OrderRefused`, which rolls everything back.
 *
 * The one event of the module dispatched inside the transaction. Nothing slow belongs here — no
 * HTTP, no letters: that is `OrderPlaced`, after the commit.
 */
final readonly class OrderPlacing
{
    public function __construct(
        public Order $order,
        public CartState $state,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
