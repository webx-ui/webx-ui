<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Events;

/**
 * A region tag is printed, and with what: its published tree, the layout's fallback view, or
 * nothing. Sent only when somebody listens, like {@see RegionRendering}.
 */
final readonly class RegionRendered
{
    public const TREE = 'tree';

    public const FALLBACK = 'fallback';

    public const EMPTY = 'empty';

    public function __construct(
        public string $name,
        public string $source,
    ) {}
}
