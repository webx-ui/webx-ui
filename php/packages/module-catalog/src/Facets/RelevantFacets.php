<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;

/**
 * A {@see FacetSource} that says which of its facets belong on a page (§4.4 of the properties
 * spec). The engine asks once per page, before it counts anything, and a facet the answer leaves
 * out is not counted at all — that is where two hundred properties stop costing two hundred
 * queries.
 *
 * What the answer cannot do is hide a chosen facet: the engine keeps it, expanded, whatever the
 * share — otherwise the choice could not be taken off.
 */
interface RelevantFacets extends FacetSource
{
    /**
     * @param  FilterState  $state  what is chosen: a category chosen in the filter is a category page too
     * @param  QueryBuilder  $products  ids of what the page found, every choice applied
     * @return list<FacetRelevance> in the order the filter shows them; a facet left out is not counted
     */
    public function relevant(FilterContext $context, FilterState $state, QueryBuilder $products): array;
}
