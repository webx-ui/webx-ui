<?php

declare(strict_types=1);

namespace WebxUi\Seo\Sitemap;

use Carbon\CarbonInterface;

/**
 * Addresses a module knows belong in the sitemap although the registry holds no row for them and
 * no named route stands behind them: the first levels of a catalogue's filter, `/laptops/brand_apple`
 * — a family too large and too changeable for rows, and too irregular for a route.
 *
 * They are checked like everything else, by the SEO resolver: an address an editor closed with a
 * rule drops out without the module hearing about it. A module registers its source from its
 * provider:
 *
 *     $this->app->make(SitemapSources::class)->register($this->app->make(FilterSitemap::class));
 */
interface SitemapSource
{
    /** The file of the map these addresses go into: `catalog-filters`. */
    public function name(): string;

    /**
     * The addresses in one language, in the registry's spelling — no language prefix, no slashes
     * on the ends. The sitemap adds the prefix, as it does for the registry's rows.
     *
     * @return iterable<array{path: string, lastmod: CarbonInterface|null}>
     */
    public function entries(string $locale): iterable;
}
