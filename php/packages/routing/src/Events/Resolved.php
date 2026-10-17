<?php

declare(strict_types=1);

namespace WebxUi\Routing\Events;

use WebxUi\Routing\Resolution;

/**
 * The resolver found the record behind an address and is handing it to the module's handler.
 * Sent only when somebody listens, like {@see Resolving}.
 */
final readonly class Resolved
{
    public function __construct(public Resolution $resolution) {}
}
