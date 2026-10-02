<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * `/robots.txt` does not answer 200, or answers with something other than plain text — a search
 * engine then reads it as "everything allowed" or, on a 5xx, as "come back later".
 */
final class RobotsMissing extends Check
{
    protected const ID = 'robots.missing';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        $robots = $context->probes->get('robots');

        if ($robots === null) {
            return;
        }

        if (! $robots->ok()) {
            yield $this->found('robots-status', ['status' => $robots->status ?? '—'], $robots->url);

            return;
        }

        $type = (string) $robots->header('content-type');

        if (! str_contains(strtolower($type), 'text/plain')) {
            yield $this->found('robots-type', ['type' => $type === '' ? '—' : $type], $robots->url);
        }
    }
}
