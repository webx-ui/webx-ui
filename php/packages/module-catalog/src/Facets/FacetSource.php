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
 *
 * {@see allFacets()} is the other list: everything a selection can be narrowed by, which only an
 * administrator's selection reads and the storefront never does.
 */
interface FacetSource
{
    /**
     * In the order the filter shows them by default.
     *
     * @return list<Facet>
     */
    public function facets(): array;

    /**
     * What the selection of a feed narrows by (§5.1 of the feeds spec): the facets above and those
     * no visitor is offered. Nothing here reaches a filter of the storefront, or its counts.
     *
     * @param  string  $locale  the language of the selection: a facet that matches words needs it
     * @return list<Facet>
     */
    public function allFacets(string $locale): array;
}
