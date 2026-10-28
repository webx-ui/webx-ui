<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/**
 * The chain's step 7, hints. Empty in this module: the satellites fill it.
 *
 * @extends Sources<HintSource>
 */
final class HintSources extends Sources
{
    public function register(HintSource $source, int $priority = 0): void
    {
        $this->add($source, $priority);
    }
}
