<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * No `<title>`, or an empty one.
 */
final class TitleMissing extends PageCheck
{
    protected const ID = 'title.missing';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if (trim((string) $page->title) === '') {
            yield $this->on($page, 'title-missing');
        }
    }
}
