<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\TitledFacet;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Sorts\Sorts;

/**
 * One page of the storefront's list, from the engine's ids to what the template draws (§8.1,
 * §10): the question, the products of the page in the engine's order, the satellites' parts
 * prepared, what can be bought, the filter, the sorts.
 *
 * The same for a category, the root and a search; what differs is the context and the scope,
 * which the caller brings.
 */
final class Listing
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Sorts $sorts,
        private readonly FilterUrls $urls,
        private readonly FilterGroups $groups,
        private readonly StorefrontParts $parts,
        private readonly Purchasability $purchasability,
        private readonly Config $config,
    ) {}

    /**
     * @param  array<string, FacetValue>  $scope  where the page stands: its category, a brand
     */
    public function page(
        FilterContext $context,
        FilterState $state,
        Request $request,
        array $scope = [],
        ?string $search = null,
        ?int $contextId = null,
    ): CatalogPage {
        $sort = $this->sortOf($request);
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = max(1, (int) $this->config->get('webx-catalog.per_page', 24));

        $query = [];

        if ($search !== null) {
            $query['q'] = $search;
        }

        if ($sort !== Sorts::DEFAULT) {
            $query['sort'] = $sort;
        }

        $context = $context->withQuery($query);

        $result = $this->catalog->engine()->search(new CatalogQuery(
            locale: $context->locale,
            context: $context->context,
            contextId: $contextId,
            scope: $scope,
            facets: $state->all(),
            count: array_map(static fn ($facet): string => $facet->key(), $context->facets),
            search: $search,
            sort: $sort,
            page: $page,
            perPage: $perPage,
            filter: $context,
        ));

        // A page past the last one is not an empty page of this list: it is no page at all.
        if ($page > 1 && $result->ids === []) {
            throw new NotFoundHttpException;
        }

        $products = $this->products($result->ids);
        $filter = $this->groups->build($context, $state, $result);
        $canonical = $this->urls->absolute($context->withQuery([]), $filter['path']);

        $paginator = new LengthAwarePaginator($products, $result->total, $perPage, $page, [
            'path' => $canonical,
            'query' => $query,
        ]);

        $this->parts->prepare($products);

        return new CatalogPage(
            path: $filter['path'],
            context: $context,
            state: $state,
            result: $result,
            products: $paginator,
            groups: $filter['groups'],
            resetUrl: $filter['reset'],
            sorts: $this->sortLinks($canonical, $sort, $search),
            sort: $sort,
            verdicts: $this->purchasability->forMany($products),
            indexable: $this->urls->indexable($context, $state, $result) && $sort === Sorts::DEFAULT && $page === 1,
            canonical: $canonical,
            heading: $this->heading($context, $state, $search),
            filterTitle: $this->filterTitle($context, $state),
            search: $search,
        );
    }

    /**
     * The products behind the ids, in the engine's order, with what a card prints loaded.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    public function products(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $position = array_flip($ids);

        return Product::query()
            ->with(['category', 'images', 'routes'])
            ->whereKey($ids)
            ->get()
            ->sortBy(static fn (Product $product): int => $position[(int) $product->id] ?? PHP_INT_MAX)
            ->values();
    }

    /** `?sort=` from the storefront's list; anything else is the default order. */
    private function sortOf(Request $request): string
    {
        $asked = $request->query('sort');

        foreach ($this->sorts->storefront() as $sort) {
            if ($sort->key() === $asked) {
                return $sort->key();
            }
        }

        return Sorts::DEFAULT;
    }

    /**
     * @return list<array{key: string, label: string, url: string, selected: bool}>
     */
    private function sortLinks(string $canonical, string $current, ?string $search): array
    {
        $links = [];

        foreach ($this->sorts->storefront() as $sort) {
            $query = array_filter([
                'q' => $search,
                'sort' => $sort->key() === Sorts::DEFAULT ? null : $sort->key(),
            ], static fn (?string $value): bool => $value !== null && $value !== '');

            $links[] = [
                'key' => $sort->key(),
                'label' => $sort->label(),
                'url' => $canonical.($query === [] ? '' : '?'.http_build_query($query)),
                'selected' => $sort->key() === $current,
            ];
        }

        return $links;
    }

    private function heading(FilterContext $context, FilterState $state, ?string $search): string
    {
        return match (true) {
            $context->context === FilterContext::SEARCH => $search === null || $search === ''
                ? (string) __('webx-catalog::storefront.search')
                : (string) __('webx-catalog::storefront.search-for', ['q' => $search]),
            $context->category !== null => $this->filterTitle($context, $state) ?? $context->category->displayName($context->locale),
            $context->subject !== null => $this->filterTitle($context, $state) ?? $context->subject->displayName($context->locale),
            default => (string) __('webx-catalog::storefront.root'),
        };
    }

    /**
     * «{category} {value}» for a page with exactly one value chosen (decision 17 of the
     * architecture) — the first level, whose title the template makes, or the facet's own
     * wording ({@see TitledFacet}). Null otherwise.
     */
    private function filterTitle(FilterContext $context, FilterState $state): ?string
    {
        if ($state->size() !== 1 || count($state->all()) !== 1) {
            return null;
        }

        $key = (string) array_key_first($state->all());
        $facet = $context->facet($key);
        $value = $state->get($key);

        if ($facet === null || $value === null || $value->isRange()) {
            return null;
        }

        $label = $facet->labels($value->values, $context->locale)[$value->values[0]] ?? null;

        if ($label === null) {
            return null;
        }

        $where = $context->category?->displayName($context->locale)
            ?? $context->subject?->displayName($context->locale)
            ?? (string) __('webx-catalog::storefront.root');

        $own = $facet instanceof TitledFacet ? $facet->filterTitle($where, $label, $context->locale) : null;

        if ($own !== null && $own !== '') {
            return $own;
        }

        return (string) __('webx-catalog::storefront.filter-title', ['category' => $where, 'value' => $label]);
    }
}
