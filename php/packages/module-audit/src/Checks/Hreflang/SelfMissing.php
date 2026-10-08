<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hreflang;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A page lists its language versions but not itself. The set has to be the same on every page
 * of it, the page's own address included — otherwise search engines may not trust the set.
 */
final class SelfMissing extends HreflangCheck
{
    protected const ID = 'hreflang.self_missing';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if (! self::names($page, $page->url)) {
            yield $this->on($page, 'hreflang-self-missing');
        }
    }
}
