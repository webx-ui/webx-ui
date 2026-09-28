<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Seo;

use WebxUi\Seo\Panel\UrlRuleSource;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * The page of a closed vacancy says `noindex` (decision 14): it stays for the links from job
 * boards and social networks, but must not hang in search as a vacancy. Out of the index, it
 * drops out of the sitemap by itself — `module-seo` leaves pages with `noindex` out.
 *
 * Under the entity's own card at 50 and over the site's defaults at 10, like the blog's tags.
 * And for the reason the tags give (`TagSource`): a rule an editor wrote in `seo_urls` for this
 * address beats it — but rules merge field by field, and a rule with an empty `robots` would
 * leave this `noindex` standing under it. So what decides is whether a rule matches at all.
 */
final class ClosedSource implements SeoSource
{
    private const PRIORITY = 40;

    public const ROBOTS = 'noindex,follow';

    public function __construct(private readonly UrlRuleSource $rules) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        if (! $subject instanceof Vacancy || ! $subject->isClosed() || $this->rules->hasRuleFor($url)) {
            return null;
        }

        return SeoData::make(['robots' => self::ROBOTS]);
    }
}
