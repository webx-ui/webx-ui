<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * A piece of a run, and the next one queued behind it (decision 5) — the way the catalogue's
 * exchange does it. A worker's time limit or a deploy cuts a run between pieces, never inside one.
 */
final class RunAuditStage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $runId) {}

    public function handle(Runner $runner, Dispatcher $bus): void
    {
        $run = AuditRun::query()->find($this->runId);

        if (! $run instanceof AuditRun || ! $run->active()) {
            return;
        }

        $budget = max(1, (int) config('webx-audit.job_seconds', 30));

        if ($runner->step($run, (float) $budget)) {
            $bus->dispatch(new self($this->runId));
        }
    }

    public function failed(?Throwable $failure): void
    {
        $run = AuditRun::query()->find($this->runId);

        if ($run instanceof AuditRun && $run->active()) {
            app(Runner::class)->fail($run, $failure?->getMessage() ?? 'The job failed.');
        }
    }
}
