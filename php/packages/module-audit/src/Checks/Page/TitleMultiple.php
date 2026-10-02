<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * More than one `<title>` — a template and a block both printing one.
 */
final class TitleMultiple extends PageCheck
{
    protected const ID = 'title.multiple';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $count = (int) $page->fact('titles', 0);

        if ($count > 1) {
            yield $this->on($page, 'title-multiple', ['count' => $count]);
        }
    }
}
