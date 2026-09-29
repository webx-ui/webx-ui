<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * One chunk of a bulk run, and the next one queued behind it (§11.4). A job per chunk rather
 * than one for the whole run: a worker's time limit, a deploy or a restart cuts a run between
 * chunks, not in the middle of forty thousand products.
 */
final class ProcessBulkChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $runId) {}

    public function handle(BulkRunner $runner, Dispatcher $bus): void
    {
        if ($runner->chunk($this->runId)) {
            $bus->dispatch(new self($this->runId));
        }
    }

    public function failed(?Throwable $failure): void
    {
        app(BulkRunner::class)->fail($this->runId);
    }
}
