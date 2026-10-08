<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * "Lorem ipsum" on a page a visitor can open: a layout's filler that nobody replaced. Drafts in
 * the database are not looked at — filler in a draft is what drafts are for.
 */
final class Placeholder extends PageCheck
{
    protected const ID = 'content.placeholder';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->fact('placeholder') === true) {
            yield $this->on($page, 'content-placeholder');
        }
    }
}
