<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/**
 * The chain's step 7, benefits. Empty in this module: the satellites fill it.
 *
 * @extends Sources<BenefitSource>
 */
final class BenefitSources extends Sources
{
    public function register(BenefitSource $source, int $priority = 0): void
    {
        $this->add($source, $priority);
    }
}
