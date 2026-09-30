<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\RelevantFacets;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Sorts\Sorts;

/**
 * One question to the engine (§8.1): where the list stands, what is chosen, what to count, in
 * what order, and which page.
 *
 * Two kinds of narrowing, and the difference is the whole point of having both. `scope` is where
 * the list stands — the category of the page, the brand of a brand page — and narrows every
 * count along with the list. `facets` is what the reader chose, and a chosen facet does not
 * narrow its own counts, so choosing Apple still shows Dell with its number beside it.
 *
 * The context is a word, not a model: `category`, `root`, `search`, `panel`, a satellite's
 * `brand`. The core knows nothing about brands; a facet that behaves differently on a brand's
 * page reads the word.
 *
 * `filter` is the page the question comes from, for a facet source that decides which of its
 * facets belong there ({@see RelevantFacets}); without it the engine makes one from the context.
 */
final class CatalogQuery
{
    public const STATE_PUBLISHED = 'published';

    public const STATE_UNPUBLISHED = 'unpublished';

    public const STATE_NO_CATEGORY = 'no-category';

    /**
     * @param  array<string, FacetValue>  $scope  facet key → the fixed part of the page
     * @param  array<string, FacetValue>  $facets  facet key → what the reader chose
     * @param  list<string>  $count  the facets to count, in order
     * @param  bool  $withUnpublished  the panel: every product, not only those on the site
     * @param  bool  $onlyTrashed  «Deleted»: only the products in the bin
     * @param  string|null  $state  the panel's own filters (§7.1), which the site does not have
     * @param  FilterContext|null  $filter  the page asking, for the sources that pick their facets
     */
    public function __construct(
        public readonly string $locale,
        public readonly string $context = 'category',
        public readonly ?int $contextId = null,
        public readonly array $scope = [],
        public readonly array $facets = [],
        public readonly array $count = [],
        public readonly ?string $search = null,
        public readonly string $sort = Sorts::DEFAULT,
        public readonly int $page = 1,
        public readonly int $perPage = 24,
        public readonly bool $withUnpublished = false,
        public readonly bool $onlyTrashed = false,
        public readonly ?string $state = null,
        public readonly ?FilterContext $filter = null,
    ) {}
}
