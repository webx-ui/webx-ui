<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Rendering\SeoData;

/**
 * What a page of the list is about when it is not a category: a satellite's entity whose page is
 * the catalogue narrowed to it — a brand (§8.1, the `scope` of `CatalogQuery`).
 *
 * The core knows nothing about brands, and does not need to: the page's heading, its trail, the
 * SEO card of its plain page and the «{where} {value}» of its first level are all it asks, and a
 * category answers the same four questions.
 */
interface ListingSubject extends HasBreadcrumbs
{
    public function displayName(string $locale): string;

    public function seoData(?string $locale = null): ?SeoData;
}
