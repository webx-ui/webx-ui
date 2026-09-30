<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * A stretch of an exchange run, and the next one queued behind it — as `ProcessBulkChunk` is for
 * a bulk action. An import does chunks for `exchange.job_seconds` and leaves the rest to the next
 * job: a worker's time limit, a deploy or a restart cuts a run between chunks, never inside one.
 * An export is one job: a file cannot be continued by another.
 */
final class ProcessExchangeChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $runId) {}

    public function handle(Importer $importer, Exporter $exporter, Dispatcher $bus): void
    {
        $run = ExchangeRun::query()->find($this->runId);

        if (! $run instanceof ExchangeRun) {
            return;
        }

        if (! $run->isImport()) {
            $exporter->work($this->runId);

            return;
        }

        $budget = max(1, (int) config('webx-catalog.exchange.job_seconds', 60));

        if ($importer->work($this->runId, (float) $budget)) {
            $bus->dispatch(new self($this->runId));
        }
    }

    public function failed(?Throwable $failure): void
    {
        $run = ExchangeRun::query()->find($this->runId);

        if ($run instanceof ExchangeRun && $run->isImport()) {
            app(Importer::class)->fail($this->runId);
        } elseif ($run instanceof ExchangeRun) {
            app(Exporter::class)->fail($this->runId);
        }
    }
}
