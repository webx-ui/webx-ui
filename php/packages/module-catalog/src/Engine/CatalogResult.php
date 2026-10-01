<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

/**
 * The engine's answer (§8.1): the ids of the page in the engine's order, how many there are in
 * all, and the counts of every facet that was asked about. The products themselves are the
 * database's to give — one `whereIn`, kept in this order ({@see Listing}).
 *
 * Two answers only a search has. `exact` — the products found whose code (the article number,
 * the barcode, the external id) is the search itself: they come first, and one of them alone takes
 * the storefront straight to its card (decisions 19–21 of the Manticore spec). `corrected` — the
 * words the list is for when the ones typed found nothing and the engine found these instead
 * (decision 16); null when the list is for what was typed.
 *
 * `fellBack` — the engine did not answer and the database did instead (decision 13): the panel says
 * so over the list, because the database searches with `LIKE` and corrects nothing.
 */
final class CatalogResult
{
    /**
     * @param  list<int>  $ids
     * @param  array<string, FacetResult>  $facets
     * @param  list<int>  $exact  at most a couple: enough to tell one from several
     */
    public function __construct(
        public readonly array $ids,
        public readonly int $total,
        public readonly array $facets = [],
        public readonly array $exact = [],
        public readonly ?string $corrected = null,
        public readonly bool $fellBack = false,
    ) {}

    public function facet(string $key): ?FacetResult
    {
        return $this->facets[$key] ?? null;
    }
}
