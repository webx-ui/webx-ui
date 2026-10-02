<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An answer slower than the threshold — to the first byte when the transport says, the whole answer otherwise.
 */
final class Ttfb extends PageCheck
{
    protected const ID = 'perf.ttfb';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $ms = $page->ttfb_ms ?? $page->total_ms;

        if ($ms !== null && $ms > $context->threshold('ttfb_ms', 600)) {
            yield $this->on($page, 'ttfb', ['ms' => $ms]);
        }
    }
}
