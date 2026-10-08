<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * `<meta http-equiv="refresh">`: the page answers 200 and then moves the visitor on — a redirect
 * search engines may or may not follow, and one that passes nothing on the way.
 */
final class MetaRefresh extends PageCheck
{
    protected const ID = 'redirects.meta_refresh';

    protected const GROUP = 'redirects';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $refresh = $page->fact('meta_refresh');

        if (is_string($refresh) && $refresh !== '') {
            yield $this->on($page, 'meta-refresh', ['value' => $refresh]);
        }
    }
}
