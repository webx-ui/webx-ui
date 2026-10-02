<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A heading level skipped on the way down — H2 straight to H4.
 */
final class HeadingsSkipped extends PageCheck
{
    protected const ID = 'headings.skipped';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $skip = $page->fact('headings_skipped');

        if (is_string($skip) && $skip !== '') {
            yield $this->on($page, 'headings-skipped', ['skip' => $skip]);
        }
    }
}
