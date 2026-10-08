<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

/**
 * What {@see SitemapReader} found: the files with their answers and counts, and the site's own
 * addresses in them. The run keeps the files (in `probes`), never the addresses.
 *
 * @phpstan-type SitemapFile array{url: string, required: bool, status: int|null, error: string|null, kind: string|null, urls: int, bytes: int, lastmod: array{count: int, future: int, distinct: int, first: string|null}, duplicates?: int, repeated?: int}
 */
final readonly class SitemapSet
{
    /**
     * @param  list<string>  $declared  The `Sitemap:` lines of robots.txt, as written.
     * @param  list<SitemapFile>  $files
     * @param  list<string>  $urls
     */
    public function __construct(
        public array $declared = [],
        public array $files = [],
        public array $urls = [],
    ) {}

    /** At least one file answered and parsed as a sitemap. */
    public function found(): bool
    {
        foreach ($this->files as $file) {
            if ($file['kind'] !== null && $file['error'] === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{declared: list<string>, files: list<SitemapFile>}
     */
    public function toArray(): array
    {
        return ['declared' => $this->declared, 'files' => $this->files];
    }
}
