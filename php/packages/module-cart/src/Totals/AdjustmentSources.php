<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/**
 * The chain's step 4, adjustments. Empty in this module: the satellites fill it.
 *
 * @extends Sources<AdjustmentSource>
 */
final class AdjustmentSources extends Sources
{
    public function register(AdjustmentSource $source, int $priority = 0): void
    {
        $this->add($source, $priority);
    }
}
