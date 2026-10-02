<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * HTML heavier than the threshold.
 */
final class HtmlSize extends PageCheck
{
    protected const ID = 'perf.html_size';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ((int) $page->bytes > $context->threshold('html_bytes', 1024 * 1024)) {
            yield $this->on($page, 'html-size', ['kb' => (int) round((int) $page->bytes / 1024)]);
        }
    }
}
