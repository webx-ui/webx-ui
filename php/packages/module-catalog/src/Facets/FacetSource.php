<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * Facets that live in the database rather than in code — the properties of a shop (§4.3 of the
 * properties spec). {@see Facets} asks once per request, on its first read, and keeps the answer
 * until {@see Facets::flush()}; keeping the list between requests, and forgetting it when it is
 * saved, is the source's own business.
 *
 * A source may also say which of its facets belong on a page ({@see RelevantFacets}): two hundred
 * properties are not two hundred blocks of a filter.
 */
interface FacetSource
{
    /**
     * In the order the filter shows them by default.
     *
     * @return list<Facet>
     */
    public function facets(): array;
}
