<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * No `meta viewport` — a phone draws the desktop page shrunk.
 */
final class Viewport extends PageCheck
{
    protected const ID = 'html.viewport';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if (! $page->fact('viewport', false)) {
            yield $this->on($page, 'viewport');
        }
    }
}
