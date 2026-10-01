<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Catalog;

use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Models\Category;

/**
 * The facets a base offers a set (§8.2 of the landings spec): the category's own set of facets, or
 * every one on the whole catalogue — the category's facet left out — each with the values its
 * products have, counted, and a range's ends. The form's builder asks it, and so does an agent.
 */
final class BaseFacets
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Facets $facets,
        private readonly CategoryFacets $categoryFacets,
    ) {}

    /**
     * @return list<array{facet: Facet, min: float|null, max: float|null, counts: array<array-key, int>}>
     */
    public function of(?Category $category, string $locale): array
    {
        $offered = array_values(array_filter(
            $category instanceof Category ? $this->categoryFacets->visible($category) : $this->facets->all(),
            static fn (Facet $facet): bool => $facet->key() !== CategoryFacet::KEY,
        ));

        $result = $this->catalog->engine()->search(new CatalogQuery(
            locale: $locale,
            context: $category instanceof Category ? FilterContext::CATEGORY : FilterContext::ROOT,
            contextId: $category?->id,
            scope: $category instanceof Category ? [CategoryFacet::KEY => FacetValue::of([(string) $category->id])] : [],
            count: array_map(static fn (Facet $facet): string => $facet->key(), $offered),
            perPage: 1,
        ));

        $data = [];

        foreach ($offered as $facet) {
            $counted = $result->facet($facet->key());
            $range = $facet->kind() === FacetKind::Range;

            $data[] = [
                'facet' => $facet,
                'min' => $range ? $counted?->min : null,
                'max' => $range ? $counted?->max : null,
                'counts' => $counted->counts ?? [],
            ];
        }

        return $data;
    }
}
