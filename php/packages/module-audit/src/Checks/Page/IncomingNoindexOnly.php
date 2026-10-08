<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An indexable page that only closed pages link to — `noindex`, robots.txt, a canonical to
 * elsewhere. To search engines it is as good as an orphan.
 */
final class IncomingNoindexOnly extends IncomingCheck
{
    protected const ID = 'structure.noindex_only';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $incoming = $this->incoming($page);

        if ($incoming['pages'] > 0 && $incoming['indexable'] === 0) {
            yield $this->on($page, 'incoming-noindex-only', ['count' => $incoming['pages']]);
        }
    }
}
