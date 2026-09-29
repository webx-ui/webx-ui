<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

/**
 * The engine's answer (§8.1): the ids of the page in the engine's order, how many there are in
 * all, and the counts of every facet that was asked about. The products themselves are the
 * database's to give — one `whereIn`, kept in this order ({@see Listing}).
 */
final class CatalogResult
{
    /**
     * @param  list<int>  $ids
     * @param  array<string, FacetResult>  $facets
     */
    public function __construct(
        public readonly array $ids,
        public readonly int $total,
        public readonly array $facets = [],
    ) {}

    public function facet(string $key): ?FacetResult
    {
        return $this->facets[$key] ?? null;
    }
}
