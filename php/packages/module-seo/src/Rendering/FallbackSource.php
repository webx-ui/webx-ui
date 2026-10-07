<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Seo\Contracts\HasSeoFallback;

/**
 * What the page is called when nobody wrote it a title: the entity's own name, lead and picture
 * ({@see HasSeoFallback}), or what the view handed in for a page that is a route rather than a
 * record — the index of recipes, the blog feed.
 *
 * Below the SEO card (`50`), because a card is written on purpose; above the defaults (`10`),
 * because a recipe's own photo says more about the recipe than the site's default picture does.
 * Before this source the views printed a `<title>` of their own when the card was empty, which
 * missed the title template, `og:title`, and every picture.
 */
final class FallbackSource implements SeoSource
{
    public function __construct(private readonly Config $config) {}

    public function priority(): int
    {
        return (int) $this->config->get('webx-seo.sources.fallbacks', 30);
    }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        return $this->answer($subject, $locale);
    }

    /**
     * The entity's answer with the view's under it: the view knows a word for the page that the
     * entity may not have in this language.
     */
    public function answer(?object $subject, ?string $locale, ?SeoData $given = null): ?SeoData
    {
        $own = $subject instanceof HasSeoFallback ? $subject->seoFallback($locale) : null;
        $data = $own !== null && $given !== null ? $own->mergeOver($given) : ($own ?? $given);

        return $data === null || $data->isEmpty() ? null : $data;
    }
}
