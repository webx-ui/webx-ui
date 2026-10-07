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
use WebxUi\Seo\Contracts\HasSeoFallback;
use WebxUi\Seo\Rendering\SeoData;

/**
 * A page of the storefront's list — a category, the root, a search — with everything its template
 * draws and everything its `<head>` says, worked out once.
 *
 * It is also what the SEO of the page is asked about ({@see ListingSource}):
 * whether it is open to the index, what it is called when a filter is chosen, where its
 * canonical points. A category is the subject of its page only when nothing is chosen, and then
 * its own SEO card speaks for it.
 *
 * `$base` is what the page stands on before the reader chooses anything: nothing on a category,
 * the set of a landing on a landing (§10.1 of the landings spec). "Nothing chosen" means "nothing
 * over the base".
 *
 * Where nobody wrote a title, the page is called its heading ({@see seoFallback()}).
 */
final class CatalogPage implements HasBreadcrumbs, HasSeoFallback
{
    public readonly FilterState $base;

    /**
     * @param  LengthAwarePaginator<int, Product>  $products
     * @param  list<FilterGroup>  $groups
     * @param  list<array{key: string, label: string, url: string, selected: bool}>  $sorts
     * @param  array<int, Verdict>  $verdicts
     * @param  string  $path  the one spelling of this page's address, as the registry writes it
     * @param  FilterState|null  $base  null is nothing
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
        ?FilterState $base = null,
    ) {
        $this->base = $base ?? FilterState::empty();
    }

    /**
     * The groups that stand open, in order.
     *
     * @return list<FilterGroup>
     */
    public function openGroups(): array
    {
        return array_values(array_filter($this->groups, static fn (FilterGroup $group): bool => $group->expanded));
    }

    /**
     * The groups under «More filters», in order.
     *
     * @return list<FilterGroup>
     */
    public function moreGroups(): array
    {
        return array_values(array_filter($this->groups, static fn (FilterGroup $group): bool => ! $group->expanded));
    }

    public function category(): ?Category
    {
        return $this->context->category;
    }

    /** The satellite's entity the page is about — a brand — when it is not a category. */
    public function subject(): ?ListingSubject
    {
        return $this->context->subject;
    }

    /**
     * Whoever speaks for the plain page with their own SEO card and texts: the subject — a brand,
     * a landing — or else the category. A landing stands on a category and still speaks for itself.
     */
    public function owner(): Category|ListingSubject|null
    {
        return $this->context->subject ?? $this->context->category;
    }

    /** Nothing chosen over the base, the default order, the first page: the page the owner's card is about. */
    public function isPlain(): bool
    {
        return ! $this->isFiltered() && ! $this->isSorted() && $this->products->currentPage() === 1;
    }

    /** The reader has chosen something over the base, or taken some of it off. */
    public function isFiltered(): bool
    {
        return ! $this->state->equals($this->base);
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
     * What the page is called when no card and no rule says: its heading — the category's name,
     * the brand's, «Catalogue» on the root. With nothing chosen over the base the owner's own
     * answer stands under it too, so its description and picture reach the snippet; once a
     * filter is chosen they are not this page's, for the same reason its SEO card is not
     * ({@see ListingSource}).
     */
    public function seoFallback(?string $locale = null): SeoData
    {
        $own = SeoData::fallback($this->heading);
        $owner = $this->owner();

        if ($this->isFiltered() || ! $owner instanceof HasSeoFallback) {
            return $own;
        }

        return $own->mergeOver($owner->seoFallback($locale ?? $this->context->locale) ?? SeoData::empty());
    }

    /**
     * The category's trail; the root and the search are a step of their own.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        $owner = $this->owner();

        if ($owner !== null) {
            return $owner->breadcrumbs($locale);
        }

        return [new Crumb($this->heading, $this->canonical)];
    }
}
