<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * One facet a {@see RelevantFacets} source keeps on a page: the share of what the page found that
 * has a value of it, and whether it stands open or under «More filters».
 */
final class FacetRelevance
{
    public function __construct(
        public readonly string $key,
        public readonly float $share = 1.0,
        public readonly bool $expanded = true,
    ) {}

    /**
     * The rule of a page that is not a category's — the search, a brand, the root (§4.4 of the
     * properties spec): by share, highest first, ties in the order given; the first `$limit` with
     * a share of at least `$minShare` open, every other one with any share at all under «More
     * filters», and one nobody found has no place in the filter.
     *
     * @param  array<string, float>  $shares  key → share, in the order ties keep (the position)
     * @return list<self>
     */
    public static function ranked(array $shares, float $minShare, int $limit): array
    {
        $order = array_flip(array_map('strval', array_keys($shares)));
        $keys = array_keys(array_filter($shares, static fn (float $share): bool => $share > 0));

        usort($keys, static fn (int|string $a, int|string $b): int => [$shares[$b], $order[(string) $a]] <=> [$shares[$a], $order[(string) $b]]);

        $ranked = [];
        $open = 0;

        foreach ($keys as $key) {
            $share = $shares[$key];
            $expanded = $share >= $minShare && $open < $limit;
            $open += $expanded ? 1 : 0;
            $ranked[] = new self((string) $key, $share, $expanded);
        }

        return $ranked;
    }
}
