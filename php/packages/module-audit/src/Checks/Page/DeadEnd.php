<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A page that links nowhere else on the site.
 */
final class DeadEnd extends PageCheck
{
    protected const ID = 'structure.dead_end';

    protected const GROUP = 'structure';

    protected const SEVERITY = Severity::NOTICE;

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->where('links_out_internal', 0);
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        yield $this->on($page, 'dead-end');
    }
}
