<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\SitemapReader;

/**
 * A sitemap file over the protocol's limits — 50 000 addresses or 50 MB uncompressed. Search
 * engines drop such a file whole, not just its tail.
 */
final class SitemapLimits extends Check
{
    protected const ID = 'sitemap.limits';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::ERROR;

    public function run(AuditContext $context): iterable
    {
        foreach ($context->probes->sitemaps->files ?? [] as $file) {
            if ($file['urls'] > SitemapReader::URLS || $file['bytes'] > SitemapReader::BYTES) {
                yield $this->found('sitemap-limits', [
                    'count' => $file['urls'],
                    'mb' => round($file['bytes'] / 1024 / 1024, 1),
                ], $file['url'], key: $file['url']);
            }
        }
    }
}
