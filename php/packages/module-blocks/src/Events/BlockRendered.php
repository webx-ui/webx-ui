<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Events;

use Throwable;
use WebxUi\Blocks\Rendering\BlockContext;

/**
 * One block's template is done — or failed, and the renderer prints its fallback instead.
 * Sent only when somebody listens, like {@see BlockRendering}.
 */
final readonly class BlockRendered
{
    public function __construct(
        public BlockContext $block,
        public ?Throwable $failure = null,
    ) {}
}
