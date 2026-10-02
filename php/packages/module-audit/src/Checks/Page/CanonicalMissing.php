<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An indexable page without a canonical, in the tag or in the header.
 */
final class CanonicalMissing extends PageCheck
{
    protected const ID = 'canonical.missing';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if (! $page->noindex() && $page->canonical === null && $page->fact('canonical_header') === null) {
            yield $this->on($page, 'canonical-missing');
        }
    }
}
