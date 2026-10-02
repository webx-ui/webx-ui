<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\Urls;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A canonical pointing at another page — a list to look through: filters and copies, or a mistake.
 */
final class CanonicalOther extends PageCheck
{
    protected const ID = 'canonical.other';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $target = $page->canonical === null ? null : Urls::normalise($page->canonical);

        if ($target !== null && $target !== $page->url) {
            yield $this->on($page, 'canonical-other', ['url' => $target]);
        }
    }
}
