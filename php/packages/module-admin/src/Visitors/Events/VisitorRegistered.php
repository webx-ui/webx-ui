<?php

declare(strict_types=1);

namespace WebxUi\Admin\Visitors\Events;

/** A new visitor's account exists; a sign-in of the same request follows as its own event. */
final readonly class VisitorRegistered
{
    /**
     * @param  string  $method  `password`, or the provider the account was made through
     */
    public function __construct(
        public int $visitorId,
        public string $method,
    ) {}
}
