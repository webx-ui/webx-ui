<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * No icon linked from the page. Whether it opens is checked with the other pictures (A3).
 */
final class Favicon extends PageCheck
{
    protected const ID = 'html.favicon';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->fact('favicon') === null) {
            yield $this->on($page, 'favicon');
        }
    }
}
