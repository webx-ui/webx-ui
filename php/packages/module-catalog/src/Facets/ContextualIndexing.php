<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use WebxUi\Catalog\Filter\FilterContext;

/**
 * A facet whose first level is open on some pages and not on others (decision 22 of the
 * properties spec): `/laptops/color_black` is a landing a reader searches for, `/brands/apple/color_black`
 * is not — the first level of a brand is its categories.
 *
 * Asked after {@see Facet::indexable()}, which still says whether the facet may be open at all.
 */
interface ContextualIndexing extends Facet
{
    public function indexableIn(FilterContext $context): bool;
}
