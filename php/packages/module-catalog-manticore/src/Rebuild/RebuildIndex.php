<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Rebuild;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use WebxUi\Catalog\Engine\Indexer;

/**
 * `webx:catalog:index --rebuild` started from «System → Search index» (decision 27): on the queue,
 * not in the request — on a hundred thousand products it is minutes, and a browser tab is no
 * place to hold them.
 *
 * One job for the whole rebuild rather than a job per batch: the new tables are filled beside the
 * live ones and swapped in at the end (decision 8), and a rebuild cut half-way never reaches the
 * swap — the storefront keeps the old tables, and a person starts it again. Once: a rebuild that
 * failed will fail the same way on a retry, and two of them at once would race for one swap.
 */
final class RebuildIndex implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct()
    {
        $this->timeout = max(60, (int) config('webx-catalog-manticore.rebuild.timeout', 3600));
        $queue = config('webx-catalog-manticore.rebuild.queue');

        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(Indexer $indexer, RebuildProgress $progress): void
    {
        $progress->start();

        try {
            $written = $indexer->rebuild(progress: static function (int $done, int $total) use ($progress): void {
                $progress->advance($done, $total);
            });
        } catch (Throwable $failure) {
            $progress->fail($failure->getMessage());

            throw $failure;
        }

        $progress->finish($written);
    }

    /** A worker killed by the timeout never reaches the catch above. */
    public function failed(?Throwable $failure): void
    {
        $progress = app(RebuildProgress::class);

        if ($progress->get()['state'] !== RebuildProgress::FAILED) {
            $progress->fail($failure?->getMessage() ?? 'The worker stopped the rebuild.');
        }
    }
}
