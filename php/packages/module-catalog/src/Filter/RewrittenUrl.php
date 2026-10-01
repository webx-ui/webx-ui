<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Filter;

/**
 * What a rewriter answers (§7.7): the path that stands for part of the state, and the rest of the
 * state, which is written after it as segments — choosing black on top of the Apple landing is
 * `/laptops-apple/color_black` (decision 26 of the architecture).
 *
 * The path is the registry's spelling: no language prefix, no slashes on the ends.
 *
 * `$indexable` opens the page to the index when the rewriter took the whole state (an empty rest),
 * however many facets it holds — a landing with products is a page of its own, not a combination
 * (§5 of the landings spec). It never closes anything: with a rest, or false, the filter's own
 * rules decide.
 */
final class RewrittenUrl
{
    public function __construct(
        public readonly string $path,
        public readonly FilterState $rest,
        public readonly bool $indexable = false,
    ) {}
}
