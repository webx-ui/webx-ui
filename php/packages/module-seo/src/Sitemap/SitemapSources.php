<?php

declare(strict_types=1);

namespace WebxUi\Seo\Sitemap;

/**
 * The modules' own addresses for the sitemap ({@see SitemapSource}), by name.
 */
final class SitemapSources
{
    /** @var array<string, SitemapSource> */
    private array $sources = [];

    public function register(SitemapSource $source): void
    {
        $this->sources[$source->name()] = $source;
    }

    /** @return list<SitemapSource> */
    public function all(): array
    {
        return array_values($this->sources);
    }
}
