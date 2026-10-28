<?php

declare(strict_types=1);

namespace WebxUi\Admin\Visitors\Events;

/**
 * A visitor signed in. Fired in the very request of the sign-in, so a listener still sees the
 * guest's cookies — the cart merges the guest's lines into the account's here.
 */
final readonly class VisitorSignedIn
{
    /**
     * @param  string  $method  how: `password`, a provider (`google`, …), `registration`, `reset`
     */
    public function __construct(
        public int $visitorId,
        public string $method,
    ) {}
}
