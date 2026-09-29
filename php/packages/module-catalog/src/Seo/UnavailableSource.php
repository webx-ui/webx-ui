<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Seo;

use WebxUi\Catalog\Models\Product;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;

/**
 * `noindex` on the page of a product that is not on sale (§5): unpublished, or published in no
 * visible category. The address answers 200 so that a link from an old order or a search result
 * still lands somewhere useful, but nothing on it is worth indexing.
 *
 * Above the entity's own card at 50: the card is what the product says about itself when it is
 * on the site, and a robots line written there for the full page must not reopen the trimmed one.
 * Below the rules an editor writes for an address at 100, which beat everything by design.
 */
final class UnavailableSource implements SeoSource
{
    private const PRIORITY = 60;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        if (! $subject instanceof Product || $subject->isVisible($locale)) {
            return null;
        }

        return SeoData::make(['robots' => 'noindex, follow']);
    }
}
