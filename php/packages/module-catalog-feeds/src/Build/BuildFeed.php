<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * A stretch of a build, and the next one queued behind it, as `ProcessExchangeChunk` is for an
 * import: pages for `job_seconds`, so a deploy or a restart cuts a build between pages. One cut
 * inside a page is repeated from the last page written (§7.1 of the feeds spec).
 *
 * **The timeout is the job's own.** A worker without `--timeout` gives a job 60 seconds, and the
 * clock runs from before the budget does: a stretch that uses its budget would be killed before it
 * queued the next one, and the same stretch would be repeated until its tries ran out. So the job
 * outlives its budget by the page in hand, and stays under `retry_after` (90 in the skeleton),
 * past which a second worker takes a job that is still alive.
 */
final class BuildFeed implements ShouldQueue
{
    use Queueable;

    /** What the page under way when the budget ends has to finish in. */
    private const PAGE_SECONDS = 20;

    public int $tries = 3;

    public int $timeout = 80;

    public function __construct(public readonly int $buildId) {}

    public function handle(FeedBuilder $builder, Dispatcher $bus): void
    {
        if ($builder->work($this->buildId, (float) $this->budget())) {
            $bus->dispatch(new self($this->buildId));
        }
    }

    /** `job_seconds`, and no more than the timeout leaves room for. */
    public function budget(): int
    {
        $budget = (int) config('webx-catalog-feeds.job_seconds', 60);

        return max(1, min($budget, $this->timeout - self::PAGE_SECONDS));
    }

    public function failed(?Throwable $failure): void
    {
        app(FeedBuilder::class)->fail($this->buildId, (string) $failure?->getMessage());
    }
}
