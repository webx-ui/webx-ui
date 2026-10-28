<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/**
 * The chain's step 2, generated lines. Empty in this module: the satellites fill it.
 *
 * @extends Sources<LineSource>
 */
final class LineSources extends Sources
{
    public function register(LineSource $source, int $priority = 0): void
    {
        $this->add($source, $priority);
    }
}
