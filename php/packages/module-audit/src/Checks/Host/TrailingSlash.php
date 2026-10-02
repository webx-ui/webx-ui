<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** `/a` and `/a/` both answer 200 — one page under two addresses. */
final class TrailingSlash extends Check
{
    protected const ID = 'host.trailing_slash';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        $inner = $context->probes->get('inner');
        $trailing = $context->probes->get('trailing');

        if ($inner !== null && $trailing !== null && $inner->ok() && $trailing->ok()) {
            yield $this->found('trailing-slash', ['url' => $inner->url, 'other' => $trailing->url], $trailing->url);
        }
    }
}
