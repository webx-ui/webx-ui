<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * No meta description, or an empty one.
 */
final class DescriptionMissing extends PageCheck
{
    protected const ID = 'description.missing';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if (trim((string) $page->description) === '') {
            yield $this->on($page, 'description-missing');
        }
    }
}
