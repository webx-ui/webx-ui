<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * An address listed twice — in one file, or in two files of an index. Search engines take it
 * once; the sitemap is just longer, and it usually means the generator walks a section twice.
 */
final class SitemapDuplicate extends Check
{
    protected const ID = 'sitemap.duplicate';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        foreach ($context->probes->sitemaps->files ?? [] as $file) {
            $duplicates = (int) ($file['duplicates'] ?? 0);
            $repeated = (int) ($file['repeated'] ?? 0);

            if ($duplicates > 0 || $repeated > 0) {
                yield $this->found('sitemap-duplicate', ['duplicates' => $duplicates, 'repeated' => $repeated], $file['url'], key: $file['url']);
            }
        }
    }
}
