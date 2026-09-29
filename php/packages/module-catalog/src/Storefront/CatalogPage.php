<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use Illuminate\Pagination\LengthAwarePaginator;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\Verdict;
use WebxUi\Catalog\Seo\ListingSource;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;

/**
 * A page of the storefront's list — a category, the root, a search — with everything its template
 * draws and everything its `<head>` says, worked out once.
 *
 * It is also what the SEO of the page is asked about ({@see ListingSource}):
 * whether it is open to the index, what it is called when a filter is chosen, where its
 * canonical points. A category is the subject of its page only when nothing is chosen, and then
 * its own SEO card speaks for it.
 */
final class CatalogPage implements HasBreadcrumbs
{
    /**
     * @param  LengthAwarePaginator<int, Product>  $products
     * @param  list<FilterGroup>  $groups
     * @param  list<array{key: string, label: string, url: string, selected: bool}>  $sorts
     * @param  array<int, Verdict>  $verdicts
     * @param  string  $path  the one spelling of this page's address, as the registry writes it
     */
    public function __construct(
        public readonly string $path,
        public readonly FilterContext $context,
        public readonly FilterState $state,
        public readonly CatalogResult $result,
        public readonly LengthAwarePaginator $products,
        public readonly array $groups,
        public readonly ?string $resetUrl,
        public readonly array $sorts,
        public readonly string $sort,
        public readonly array $verdicts,
        public readonly bool $indexable,
        public readonly string $canonical,
        public readonly string $heading,
        public readonly ?string $filterTitle = null,
        public readonly ?string $search = null,
    ) {}

    public function category(): ?Category
    {
        return $this->context->category;
    }

    /** Nothing chosen, the default order, the first page: the page the category's card is about. */
    public function isPlain(): bool
    {
        return $this->state->isEmpty() && ! $this->isSorted() && $this->products->currentPage() === 1;
    }

    public function isSorted(): bool
    {
        return isset($this->context->query['sort']);
    }

    public function verdict(Product $product): Verdict
    {
        return $this->verdicts[(int) $product->id] ?? Verdict::yes();
    }

    /**
     * The category's trail; the root and the search are a step of their own.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        $category = $this->category();

        if ($category !== null) {
            return $category->breadcrumbs($locale);
        }

        return [new Crumb($this->heading, $this->canonical)];
    }
}
