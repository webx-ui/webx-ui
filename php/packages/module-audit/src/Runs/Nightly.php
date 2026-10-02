<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * The run the scheduler starts at night, started by `schedule`. In the queue like one from the
 * panel; on a `sync` queue it runs right here, piece after piece — the scheduler is a process of
 * its own, the one place a long run hurts nobody's request.
 */
final class Nightly
{
    public function __construct(
        private readonly Runner $runner,
        private readonly Dispatcher $bus,
        private readonly Config $config,
    ) {}

    public function run(string $scope): ?AuditRun
    {
        // A run that is still going — last night's, or one somebody started — is not doubled.
        if (AuditRun::query()->active()->exists()) {
            return null;
        }

        $run = $this->runner->start(in_array($scope, [AuditRun::FULL, AuditRun::QUICK], true) ? $scope : AuditRun::FULL, 'schedule');

        $connection = (string) $this->config->get('queue.default', 'sync');

        if ($this->config->get("queue.connections.{$connection}.driver", $connection) === 'sync') {
            return $this->runner->complete($run);
        }

        $this->bus->dispatch(new RunAuditStage($run->id));

        return $run;
    }
}
