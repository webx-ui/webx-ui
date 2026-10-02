<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * More than one H1.
 */
final class H1Multiple extends PageCheck
{
    protected const ID = 'h1.multiple';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $count = (int) ($page->headings['h1'] ?? 0);

        if ($count > 1) {
            yield $this->on($page, 'h1-multiple', ['count' => $count]);
        }
    }
}
