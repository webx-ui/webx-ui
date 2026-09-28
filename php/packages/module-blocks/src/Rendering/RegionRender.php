<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use WebxUi\Blocks\BlockType;

/**
 * A region's tree, rendered in a frame of its own: the markup, the types it printed — its own
 * bundle, apart from the page's — and every block that threw on the way.
 *
 * The failures are what a region is judged by: on the site one failed block replaces the whole
 * region with its fallback, and publishing refuses a tree that has any.
 */
final readonly class RegionRender
{
    /**
     * @param  array<string, BlockType>  $types
     * @param  list<array{key: string, type: string, message: string, line: int|null}>  $failures
     */
    public function __construct(
        public string $html,
        public array $types,
        public array $failures,
    ) {}

    public function failed(): bool
    {
        return $this->failures !== [];
    }
}
