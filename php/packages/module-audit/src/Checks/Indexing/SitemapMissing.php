<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;

/**
 * No sitemap answers and parses — or a file that robots.txt or an index names does not.
 */
final class SitemapMissing extends Check
{
    protected const ID = 'sitemap.missing';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::ERROR;

    public function run(AuditContext $context): iterable
    {
        $sitemaps = $context->probes->sitemaps;

        if ($sitemaps === null) {
            return;
        }

        $rows = [];

        foreach ($sitemaps->files as $file) {
            $broken = $file['status'] !== 200 || $file['kind'] === null || $file['error'] !== null;

            if ($broken && ($file['required'] || ! $sitemaps->found())) {
                $rows[] = ['url' => $file['url'], 'status' => $file['status'], 'error' => $file['error']];
            }
        }

        if ($rows === []) {
            return;
        }

        yield $this->found($sitemaps->found() ? 'sitemap-broken-files' : 'sitemap-missing', ['count' => count($rows)], $rows[0]['url'], table: [
            'columns' => [Finding::column('url', 'url'), Finding::column('status', 'status'), Finding::column('error')],
            'rows' => $rows,
        ]);
    }
}
