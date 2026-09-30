<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Filter;

use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Storefront\ListingSubject;

/**
 * Where a filter stands: the page's own address, the category of the page if it is one — or the
 * satellite's entity it is about, a brand — the facets this page may be filtered by, and the
 * language.
 *
 * `$path` is the registry's spelling — no language prefix, no slashes on the ends —
 * `gaming-laptops` or `catalog`. The language prefix and the host are added once, at the end,
 * by {@see FilterUrls}.
 */
final class FilterContext
{
    public const CATEGORY = 'category';

    public const ROOT = 'root';

    public const SEARCH = 'search';

    /**
     * @param  list<Facet>  $facets  the facets this page shows, and therefore the ones its address may name
     * @param  array<string, string>  $query  what every link keeps from the query: the sort, the search
     */
    public function __construct(
        public readonly string $context,
        public readonly string $path,
        public readonly string $locale,
        public readonly array $facets,
        public readonly ?Category $category = null,
        public readonly array $query = [],
        public readonly ?ListingSubject $subject = null,
    ) {}

    public function facet(string $key): ?Facet
    {
        foreach ($this->facets as $facet) {
            if ($facet->key() === $key) {
                return $facet;
            }
        }

        return null;
    }

    /** The facet this page shows under this code in the page's language. */
    public function facetByCode(string $code): ?Facet
    {
        foreach ($this->facets as $facet) {
            if ($facet->code($this->locale) === $code) {
                return $facet;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $query
     */
    public function withQuery(array $query): self
    {
        return new self($this->context, $this->path, $this->locale, $this->facets, $this->category, $query, $this->subject);
    }
}
