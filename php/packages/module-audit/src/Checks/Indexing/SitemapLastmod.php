<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * `lastmod` in the future, or the same on every address — the date is then printed rather than
 * kept, and search engines learn to ignore it.
 */
final class SitemapLastmod extends Check
{
    protected const ID = 'sitemap.lastmod';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        foreach ($context->probes->sitemaps->files ?? [] as $file) {
            $lastmod = $file['lastmod'];

            if ($lastmod['future'] > 0) {
                yield $this->found('sitemap-lastmod-future', ['count' => $lastmod['future']], $file['url'], key: 'future');
            }

            if ($file['kind'] === 'urlset' && $lastmod['count'] > 1 && $lastmod['distinct'] === 1) {
                yield $this->found('sitemap-lastmod-same', ['value' => (string) $lastmod['first'], 'count' => $lastmod['count']], $file['url'], key: 'same');
            }
        }
    }
}
