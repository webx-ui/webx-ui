<?php

declare(strict_types=1);

namespace WebxUi\Admin\Visitors\Events;

/** A visitor signed out; fired before the session is let go. */
final readonly class VisitorSignedOut
{
    public function __construct(
        public int $visitorId,
    ) {}
}
