<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Filter;

/**
 * Something that gives a set of chosen values an address of its own (§7.7, decision 26 of the
 * architecture): a landing page takes `/laptops/brand_apple` and names it `/laptops-apple`, and
 * every link of the filter that chooses Apple points there straight away, without a 301.
 *
 * The core has none; `module-catalog-landings` is the first.
 */
interface FilterUrlRewriter
{
    /**
     * Called once per page render with every state the filter is about to link to, so a rewriter
     * loads what it needs — the landings of this category — in one query rather than one a link.
     *
     * @param  list<FilterState>  $states
     */
    public function prepare(FilterContext $context, array $states): void;

    /** The address that stands for this state, or null to leave it to the next rewriter. */
    public function rewrite(FilterContext $context, FilterState $state): ?RewrittenUrl;
}
