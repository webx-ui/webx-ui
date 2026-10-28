<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/**
 * The chain's step 5, tenders. Empty in this module: the satellites fill it.
 *
 * @extends Sources<TenderSource>
 */
final class TenderSources extends Sources
{
    public function register(TenderSource $source, int $priority = 0): void
    {
        $this->add($source, $priority);
    }
}
