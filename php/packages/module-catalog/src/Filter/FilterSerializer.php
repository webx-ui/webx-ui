<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Filter;

/**
 * The format of a filter's address (§7.7, §8.1 of the architecture), behind an interface: the
 * facets do not know it, and nor does anything that asks for a link.
 */
interface FilterSerializer
{
    /**
     * The state a tail names, or null when the tail is not a filter of this page — a segment
     * without `_`, a facet this page does not show, a value nobody has: a 404.
     */
    public function parse(FilterContext $context, string $tail): ?FilterState;

    /**
     * The address of each state, in one pass: a filter draws hundreds of links, and the slugs of
     * all of them are looked up together. The registry's spelling — no language prefix.
     *
     * @param  list<FilterState>  $states
     * @return list<string>
     */
    public function buildMany(FilterContext $context, array $states): array;

    /**
     * Only the segments of each state, for the rest a rewriter left over.
     *
     * @param  list<FilterState>  $states
     * @return list<string>
     */
    public function tails(FilterContext $context, array $states): array;
}
