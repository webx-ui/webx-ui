<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Seo;

use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\ContextualIndexing;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterSerializer;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Catalog\Models\Category;
use WebxUi\Seo\Sitemap\SitemapSource;

/**
 * The first levels of the filter that are not empty (§10.3): `/laptops/brand_apple` for every
 * visible category, every indexable facet it shows and every value with products behind it.
 *
 * The categories and the products are in the map already — they are rows of the registry. What
 * is here is what the registry does not hold. An address a rewriter gives to a landing is the
 * landing's, and the landing is a row of its own: it is left out here rather than written twice.
 *
 * One count per category, of the indexable facets only — on the database engine that is a
 * `group by` per facet per category, which is what a map built once a day can afford.
 */
final class FilterSitemap implements SitemapSource
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly CategoryFacets $facets,
        private readonly FilterUrls $urls,
        private readonly FilterSerializer $serializer,
    ) {}

    public function name(): string
    {
        return 'catalog-filters';
    }

    /**
     * @return iterable<array{path: string, lastmod: null}>
     */
    public function entries(string $locale): iterable
    {
        foreach (Category::query()->visible()->orderBy('lft')->lazyById(200) as $category) {
            if (! $category->hasUrlIn($locale)) {
                continue;
            }

            $open = array_values(array_filter(
                $this->facets->visible($category),
                static fn ($facet): bool => $facet->indexable() && $facet->key() !== CategoryFacet::KEY,
            ));

            if ($open === []) {
                continue;
            }

            $context = new FilterContext(
                FilterContext::CATEGORY,
                $category->routeCanonical($locale)->path ?? $category->routePath($locale),
                $locale,
                $open,
                $category,
            );

            $open = array_values(array_filter(
                $open,
                static fn ($facet): bool => ! $facet instanceof ContextualIndexing || $facet->indexableIn($context),
            ));

            if ($open === []) {
                continue;
            }

            $result = $this->catalog->engine()->search(new CatalogQuery(
                locale: $locale,
                context: FilterContext::CATEGORY,
                contextId: (int) $category->id,
                scope: [CategoryFacet::KEY => FacetValue::of([(string) $category->id])],
                count: array_map(static fn ($facet): string => $facet->key(), $open),
                perPage: 1,
                filter: $context,
            ));

            $states = [];

            foreach ($open as $facet) {
                foreach ($result->facet($facet->key())->counts ?? [] as $value => $count) {
                    if ($count > 0) {
                        $states[] = FilterState::of([$facet->key() => FacetValue::of([(string) $value])]);
                    }
                }
            }

            if ($states === []) {
                continue;
            }

            $plain = $this->serializer->buildMany($context, $states);

            foreach ($this->urls->paths($context, $states) as $i => $path) {
                if ($path === $plain[$i]) {
                    yield ['path' => $path, 'lastmod' => null];
                }
            }
        }
    }
}
