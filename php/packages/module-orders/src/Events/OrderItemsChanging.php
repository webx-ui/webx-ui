<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use WebxUi\Orders\Data\RevisionData;
use WebxUi\Orders\Models\Order;

/**
 * The lines are edited and not committed yet (§39), as `OrderPlacing` is for a new order: the
 * stock reserves the lines added and lets go of those taken out here, or refuses with
 * `OrderRefused` — and the edit is rolled back whole. `order` is the model with its new lines.
 * The locks go the order first, then the listener's sources.
 */
final readonly class OrderItemsChanging
{
    public function __construct(
        public Order $order,
        public RevisionData $revision,
    ) {}
}
