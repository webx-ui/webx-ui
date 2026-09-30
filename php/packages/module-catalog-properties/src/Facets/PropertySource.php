<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\FacetRelevance;
use WebxUi\Catalog\Facets\RelevantFacets;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Search\SearchContributor;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\Localization\Locales;

/**
 * The properties as the catalogue's facets (§4.3–§4.5 of the properties spec): the source the core
 * asks lazily, which of them belong on a page, and the words the search finds products by.
 *
 * **On a category's page** — or with one category chosen in the filter — the facets are the set of
 * that category marked «in the filter», all open: in the order of its «Filters» tab where the tab
 * names them, in the order of the set otherwise. **Anywhere else** — the search, a brand, the root —
 * they are picked by coverage: the share of what the page found that has a value of the property,
 * counted once for all properties; the first `dynamic_facets.limit` with at least
 * `dynamic_facets.min_share` open, the rest with any share under «More filters» ({@see
 * FacetRelevance::ranked()}). The core keeps a chosen facet open whatever the share.
 */
final class PropertySource implements RelevantFacets, SearchContributor
{
    public function __construct(
        private readonly Properties $properties,
        private readonly PropertySets $sets,
        private readonly CategoryFacets $categoryFacets,
        private readonly Locales $locales,
        private readonly Config $config,
    ) {}

    public function facets(): array
    {
        $facets = [];

        foreach ($this->properties->all() as $property) {
            if ($property->is_filterable && ! $property->isText()) {
                $facets[] = PropertyFacet::for($property);
            }
        }

        return $facets;
    }

    public function relevant(FilterContext $context, FilterState $state, QueryBuilder $products): array
    {
        $category = $this->category($context, $state);

        if ($category !== null) {
            return $this->ofCategory($category);
        }

        $filterable = array_filter($this->properties->all(), static fn (Property $property): bool => $property->is_filterable && ! $property->isText());

        if ($filterable === []) {
            return [];
        }

        $total = (int) DB::query()->fromSub(clone $products, 'found')->count();

        if ($total === 0) {
            return [];
        }

        $rows = DB::table(FacetRows::TABLE)
            ->whereIn('product_id', clone $products)
            ->whereIn('property_id', array_keys($filterable))
            ->groupBy('property_id')
            ->selectRaw('property_id, count(distinct product_id) as products');

        $having = $this->sets->whereInSet($rows)->pluck('products', 'property_id');
        $shares = [];

        foreach ($filterable as $id => $property) {
            $shares[$property->facetKey()] = (int) ($having[$id] ?? 0) / $total;
        }

        return FacetRelevance::ranked(
            $shares,
            (float) $this->config->get('webx-catalog-properties.dynamic_facets.min_share', 0.1),
            (int) $this->config->get('webx-catalog-properties.dynamic_facets.limit', 8),
        );
    }

    /**
     * The values of reference books by name, and texts, of the properties searched by value —
     * only of a property in the set of the product's main category.
     *
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, string $text, string $locale): void
    {
        $searched = array_keys(array_filter($this->properties->all(), static fn (Property $property): bool => $property->is_searchable));

        if ($searched === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $text).'%';
        $codes = array_values(array_unique([$locale, ...$this->locales->codes()]));

        $matching = $query->getQuery()->newQuery()
            ->from(FacetRows::TABLE)
            ->leftJoin('catalog_property_values as book', 'book.id', '=', FacetRows::TABLE.'.value_id')
            ->select(FacetRows::TABLE.'.product_id')
            ->whereIn(FacetRows::TABLE.'.property_id', $searched)
            ->where(static function (QueryBuilder $any) use ($codes, $like): void {
                foreach ($codes as $code) {
                    $any->orWhere('book.title->'.$code, 'like', $like)
                        ->orWhere(FacetRows::TABLE.'.text->'.$code, 'like', $like);
                }
            });

        $this->sets->whereInSet($matching);

        $query->whereIn($query->getModel()->qualifyColumn('id'), $matching);
    }

    /**
     * The category's set marked «in the filter», open, in the tab's order and then the set's.
     *
     * @return list<FacetRelevance>
     */
    private function ofCategory(Category $category): array
    {
        $all = $this->properties->all();
        $inSet = [];

        foreach ($this->sets->effective($category) as $id) {
            $property = $all[$id] ?? null;

            if ($property !== null && $property->is_filterable && ! $property->isText()) {
                $inSet[$property->facetKey()] = true;
            }
        }

        $ordered = [];

        foreach ($this->categoryFacets->resolve($category)['facets'] ?? [] as $row) {
            if (isset($inSet[$row['key']])) {
                if ($row['visible']) {
                    $ordered[$row['key']] = true;
                }

                unset($inSet[$row['key']]);
            }
        }

        return array_map(
            static fn (string $key): FacetRelevance => new FacetRelevance($key),
            [...array_keys($ordered), ...array_keys($inSet)],
        );
    }

    /** The category the page stands in, or the one category chosen in its filter. */
    private function category(FilterContext $context, FilterState $state): ?Category
    {
        if ($context->context === FilterContext::CATEGORY && $context->category instanceof Category) {
            return $context->category;
        }

        $chosen = $state->get(CategoryFacet::KEY);

        if ($chosen === null || count($chosen->values) !== 1) {
            return null;
        }

        $category = Category::query()->find((int) $chosen->values[0]);

        return $category instanceof Category ? $category : null;
    }
}
