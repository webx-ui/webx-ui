<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An `iframe` without a `title`.
 */
final class IframeTitle extends PageCheck
{
    protected const ID = 'a11y.iframe_title';

    protected const GROUP = 'a11y';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $count = (int) $page->fact('iframes_untitled', 0);

        if ($count > 0) {
            yield $this->on($page, 'iframe-title', ['count' => $count]);
        }
    }
}
