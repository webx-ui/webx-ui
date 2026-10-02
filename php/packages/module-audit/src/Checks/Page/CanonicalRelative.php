<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\Urls;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A canonical written as a path — search engines want the whole address.
 */
final class CanonicalRelative extends PageCheck
{
    protected const ID = 'canonical.relative';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $raw = $page->fact('canonicals', [])[0] ?? null;

        if (is_string($raw) && $raw !== '' && ! Urls::absolute($raw)) {
            yield $this->on($page, 'canonical-relative', ['value' => $raw]);
        }
    }
}
