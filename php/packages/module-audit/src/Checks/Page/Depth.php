<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An indexable page further from the home page than the threshold, in clicks.
 */
final class Depth extends PageCheck
{
    protected const ID = 'structure.depth';

    protected const GROUP = 'structure';

    protected const SEVERITY = Severity::NOTICE;

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->where('indexable', true)->where('depth', '>', $context->threshold('depth', 3));
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        yield $this->on($page, 'depth', ['depth' => (int) $page->depth]);
    }
}
