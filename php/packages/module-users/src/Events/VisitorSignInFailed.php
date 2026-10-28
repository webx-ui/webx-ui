<?php

declare(strict_types=1);

namespace WebxUi\Users\Events;

/**
 * A sign-in refused for a wrong pair (spec §15), for whoever builds signals on it later. No
 * password and no address in the open: the sha256 of the normalised address, which tells one
 * address's run of attempts from another's and nothing more. There is no journal of them.
 */
final readonly class VisitorSignInFailed
{
    public function __construct(
        public string $emailHash,
        public ?string $ip,
    ) {}
}
