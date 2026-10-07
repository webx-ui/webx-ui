<?php

declare(strict_types=1);

namespace WebxUi\Seo\Contracts;

use WebxUi\Seo\Rendering\SeoData;

/**
 * What an entity says about its page when nobody wrote it an SEO card: its own name, its lead,
 * its picture.
 *
 * Asked by {@see \WebxUi\Seo\Rendering\FallbackSource}, which stands below the card and above the
 * site defaults. So a card wins every field it fills in, and the recipe's own photo wins over
 * the site's default social image — the one that is "shown when a page has no picture of its
 * own" and should be exactly that.
 *
 * The title is the bare name. It goes through the title template like every other title, so
 * the site's name is added once, by the template, and not typed into a view.
 *
 * Build the answer with {@see SeoData::fallback()}: it strips the markup out of a lead and keeps
 * a picture only when it has an absolute address.
 */
interface HasSeoFallback
{
    public function seoFallback(?string $locale = null): ?SeoData;
}
