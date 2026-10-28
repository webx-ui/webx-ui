<?php

declare(strict_types=1);

namespace WebxUi\Admin\Visitors\Events;

/**
 * A visitor's account is about to be deleted for good, inside the transaction that deletes it.
 *
 * A listener lets go of what it keeps for the visitor — orders lose their `user_id`, the cart goes —
 * while the row is still there; a listener that throws stops the deletion.
 */
final readonly class VisitorDeleting
{
    public function __construct(
        public int $visitorId,
    ) {}
}
