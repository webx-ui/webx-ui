<?php

declare(strict_types=1);

namespace WebxUi\Routing\Events;

/**
 * The resolver is about to look an address up. Sent only when somebody listens — the debug bar
 * measures the lookup with it — so a site without a listener pays one check of an array.
 */
final readonly class Resolving
{
    public function __construct(public string $path) {}
}
