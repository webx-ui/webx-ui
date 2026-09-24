<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Seo;

use WebxUi\Recipes\Rendering\Catalog;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;

/**
 * `noindex` on a catalogue narrowed to one nutrient (§5.4) — the index, a category page, or any
 * page with the catalogue on it as a block: whoever prints it, the address carries `?nutrient=`.
 *
 * The canonical needs nothing from here: `module-seo` names the page itself with only the query
 * that makes a different page (`?page=`), so the filter drops out of it by itself. What is left
 * is to say that the filtered copy is not a page of its own.
 *
 * Under the entity's own card (50) and a rule by address (100): a site that wants the filtered
 * pages in the index writes a rule for them and wins.
 */
final class FilteredCatalogSource implements SeoSource
{
    private const PRIORITY = 45;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        $query = explode('?', $url, 2)[1] ?? '';

        parse_str($query, $parameters);

        $nutrient = $parameters[Catalog::NUTRIENT] ?? null;

        if (! is_string($nutrient) || $nutrient === '') {
            return null;
        }

        return SeoData::make(['robots' => 'noindex, follow']);
    }
}
