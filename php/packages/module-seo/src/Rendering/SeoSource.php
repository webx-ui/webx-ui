<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

/**
 * Somewhere a page's SEO can come from.
 *
 * The resolver knows nothing about tables, settings or entities — it asks whoever is registered,
 * in order, and merges the answers. That is what lets `HasSeo` arrive later as one more source
 * rather than as a change to the resolver.
 *
 * A source answers with the fields it actually has and leaves the rest null; whatever stands
 * below it fills those in.
 */
interface SeoSource
{
    /** Higher is asked first, and wins a field it fills in. */
    public function priority(): int;

    /**
     * @param  string  $url  Path and query as the request had it, normalised.
     * @param  object|null  $subject  The entity being rendered, when the template named one.
     * @param  string|null  $locale  The language asked for; null means the current one.
     */
    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData;
}
