<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An indexable page every internal link to which has `nofollow`: the site asks search engines
 * not to go there, and then wants it found.
 */
final class IncomingNofollowOnly extends IncomingCheck
{
    protected const ID = 'structure.nofollow_only';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $incoming = $this->incoming($page);

        if ($incoming['pages'] > 0 && $incoming['followed'] === 0) {
            yield $this->on($page, 'incoming-nofollow-only', ['count' => $incoming['pages']]);
        }
    }
}
