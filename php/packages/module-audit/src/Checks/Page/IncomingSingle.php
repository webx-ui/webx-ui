<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An indexable page only one other page links to: one changed menu or one removed list away from
 * an orphan, and given little weight meanwhile. Fine for an article in a list; worth a look for
 * a page that matters.
 */
final class IncomingSingle extends IncomingCheck
{
    protected const ID = 'structure.single_link';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($this->incoming($page)['pages'] === 1) {
            yield $this->on($page, 'incoming-single');
        }
    }
}
