<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Config;

use WebxUi\Admin\Doctor\QueueBacklog;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * The database queue has jobs nobody takes: inbox letters, audit runs and image work stay
 * "queued" for ever while everything else looks fine. The same question `webx:doctor` asks, by
 * the same {@see QueueBacklog}, so the two never disagree.
 *
 * Apart from `config.queue` because the problem and the fix are different — that one is a
 * queue that runs inside the request, this one is a queue that does not run at all.
 */
final class QueueWorker extends Check
{
    protected const ID = 'config.queue_worker';

    protected const GROUP = 'config';

    protected const SEVERITY = Severity::ERROR;

    protected const NEEDS = ['config'];

    public function __construct(private readonly QueueBacklog $queue) {}

    public function run(AuditContext $context): iterable
    {
        $stalled = $this->queue->stalled();

        if ($stalled > 0) {
            // A stand where nobody started a worker is a stand; a live site that sends no letters
            // is the worst thing this group can find.
            yield $this->found(
                'queue-stalled',
                ['connection' => $this->queue->connection(), 'count' => $stalled, 'minutes' => QueueBacklog::MINUTES],
                severity: $context->production() ? Severity::ERROR : Severity::WARNING,
            );
        }
    }
}
