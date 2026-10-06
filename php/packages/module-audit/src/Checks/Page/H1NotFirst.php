<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * The page has an H1, but another heading comes before it — usually a block above the content
 * that took a heading level for its looks.
 */
final class H1NotFirst extends PageCheck
{
    protected const ID = 'headings.h1_not_first';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $outline = Outline::of($page);

        if ($outline === [] || $outline[0][0] === 1 || (int) ($page->headings['h1'] ?? 0) === 0) {
            return;
        }

        yield $this->on($page, 'headings-h1-not-first', ['level' => 'H'.$outline[0][0], 'value' => $outline[0][1]]);
    }
}
