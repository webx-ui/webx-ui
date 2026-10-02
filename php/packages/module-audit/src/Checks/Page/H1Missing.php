<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * No H1 on the page.
 */
final class H1Missing extends PageCheck
{
    protected const ID = 'h1.missing';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ((int) ($page->headings['h1'] ?? 0) === 0) {
            yield $this->on($page, 'h1-missing');
        }
    }
}
