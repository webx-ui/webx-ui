<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\RelevantFacets;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Models\Category;

/**
 * Which of the facets asked about belong on the page, open or not and in what place — asked of
 * the sources that pick their facets ({@see RelevantFacets}) before anything is counted. The same
 * answer for every engine, so it lives apart from all of them.
 */
final class FacetPlacement
{
    public function __construct(private readonly Facets $facets) {}

    /** Whether any source asked about here picks its facets — whether {@see place()} needs the products. */
    public function picks(CatalogQuery $query): bool
    {
        foreach ($this->facets->sources() as $source) {
            if ($source instanceof RelevantFacets && $this->own($query, $source) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the sources that pick their facets keep on this page, and how: key → open or not, and
     * the place. Null when no source picks — every facet asked about is counted. A facet of such a
     * source that is not in the answer is left out, unless it is chosen.
     *
     * @param  QueryBuilder  $found  ids of what the page found, every choice applied
     * @return array<string, array{expanded: bool, rank: int|null}>|null
     */
    public function place(CatalogQuery $query, QueryBuilder $found): ?array
    {
        $placing = null;
        $context = null;

        foreach ($this->facets->sources() as $source) {
            if (! $source instanceof RelevantFacets) {
                continue;
            }

            $own = $this->own($query, $source);

            if ($own === []) {
                continue;
            }

            if ($placing === null) {
                $placing = [];

                // Everything else asked about is counted as it always was.
                foreach ($query->count as $key) {
                    if ($this->facets->sourceOf($key) === null) {
                        $placing[$key] = ['expanded' => true, 'rank' => null];
                    }
                }
            }

            $context ??= $query->filter ?? $this->context($query);
            $rank = 0;

            foreach ($source->relevant($context, FilterState::of($query->facets), clone $found) as $relevance) {
                if (in_array($relevance->key, $own, true) && ! isset($placing[$relevance->key])) {
                    $placing[$relevance->key] = ['expanded' => $relevance->expanded, 'rank' => $rank++];
                }
            }

            // A chosen facet stays, open, whatever its share: otherwise the choice cannot be undone.
            foreach ($own as $key) {
                if (self::chosen($query, $key)) {
                    $placing[$key] = ['expanded' => true, 'rank' => $placing[$key]['rank'] ?? $rank++];
                }
            }
        }

        return $placing;
    }

    /**
     * The counted facets, in the order asked, each placed as {@see place()} said.
     *
     * @param  array<string, FacetResult>  $counted
     * @param  array<string, array{expanded: bool, rank: int|null}>|null  $placing
     * @return array<string, FacetResult>
     */
    public static function arrange(CatalogQuery $query, array $counted, ?array $placing): array
    {
        $facets = [];

        foreach ($query->count as $key) {
            if (isset($counted[$key]) && ($placing === null || isset($placing[$key]))) {
                $place = $placing[$key] ?? null;
                $facets[$key] = $place === null ? $counted[$key] : $counted[$key]->placed($place['expanded'], $place['rank']);
            }
        }

        return $facets;
    }

    public static function chosen(CatalogQuery $query, string $key): bool
    {
        return isset($query->facets[$key]) && ! $query->facets[$key]->isEmpty();
    }

    /** @return list<string> */
    private function own(CatalogQuery $query, RelevantFacets $source): array
    {
        return array_values(array_filter($query->count, fn (string $key): bool => $this->facets->sourceOf($key) === $source));
    }

    /** The page a question without one comes from, as far as the question says. */
    private function context(CatalogQuery $query): FilterContext
    {
        $facets = array_values(array_filter(array_map(fn (string $key) => $this->facets->find($key), $query->count)));
        $category = $query->context === FilterContext::CATEGORY && $query->contextId !== null
            ? Category::query()->find($query->contextId)
            : null;

        return new FilterContext($query->context, '', $query->locale, $facets, $category instanceof Category ? $category : null);
    }
}
