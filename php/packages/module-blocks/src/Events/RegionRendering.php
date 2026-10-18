<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Events;

/** A region tag starts printing. Sent only when somebody listens (the debug bar's timeline). */
final readonly class RegionRendering
{
    public function __construct(public string $name) {}
}
