<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Config;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** The `sync` queue: submissions and letters are handled inside the visitor's request. */
final class Queue extends Check
{
    protected const ID = 'config.queue';

    protected const GROUP = 'config';

    protected const SEVERITY = Severity::WARNING;

    protected const NEEDS = ['config'];

    public function run(AuditContext $context): iterable
    {
        $connection = (string) $context->config('queue.default', 'sync');
        $driver = (string) $context->config("queue.connections.{$connection}.driver", $connection);

        if ($context->production() && $driver === 'sync') {
            yield $this->found('queue-sync', ['connection' => $connection]);
        }
    }
}
