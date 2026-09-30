<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Seo;

use WebxUi\Catalog\Storefront\CatalogPage;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;

/**
 * What a page of the storefront's list says about itself (§10.3).
 *
 * - Nothing chosen, first page, default order: the category's own SEO card, as on any entity.
 * - One value of an indexable facet: open, titled by the template «{category} {value}»
 *   (decision 17 of the architecture); the category's card is not this page's.
 * - Any other choice, any `?sort=`, any `?page=` past the first, any search: `noindex, follow`,
 *   the canonical on the page without its query (§7.2).
 *
 * At 60 with {@see UnavailableSource}: above the entity's card at 50, which it partly speaks for,
 * and under the rules an editor writes for one address at 100 — a rule for a filter page beats
 * all of this, which is how an SEO person opens a combination by hand.
 */
final class ListingSource implements SeoSource
{
    private const PRIORITY = 60;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        if (! $subject instanceof CatalogPage) {
            return null;
        }

        // A brand's page is a category's page in every respect that matters here.
        $owner = $subject->owner();
        $base = $subject->state->isEmpty() && $owner !== null ? ($owner->seoData($locale) ?? SeoData::empty()) : SeoData::empty();

        $own = SeoData::make([
            'title' => $subject->state->isEmpty() && $owner !== null ? null : ($subject->filterTitle ?? $subject->heading),
            'h1' => $subject->filterTitle,
            'robots' => $subject->indexable ? null : 'noindex, follow',
            // Only where a query makes the address another page; otherwise the page names itself.
            'canonical' => $subject->isSorted() || $subject->products->currentPage() > 1 ? $subject->canonical : null,
        ]);

        return $own->mergeOver($base);
    }
}
