<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Events;

use WebxUi\Blocks\Rendering\BlockContext;

/**
 * One block's template starts running — every block passes here, a page's, a region's and one
 * called from another template. Sent only when somebody listens (the debug bar), so a page of
 * two hundred blocks costs nothing more without one.
 */
final readonly class BlockRendering
{
    public function __construct(public BlockContext $block) {}
}
