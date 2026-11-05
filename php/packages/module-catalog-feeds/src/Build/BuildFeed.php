<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * A stretch of a build, and the next one queued behind it, as `ProcessExchangeChunk` is for an
 * import: pages for `job_seconds`, so a worker's limit, a deploy or a restart cuts a build between
 * pages. One cut inside a page is repeated from the last page written (§7.1 of the feeds spec).
 */
final class BuildFeed implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $buildId) {}

    public function handle(FeedBuilder $builder, Dispatcher $bus): void
    {
        $budget = max(1, (int) config('webx-catalog-feeds.job_seconds', 60));

        if ($builder->work($this->buildId, (float) $budget)) {
            $bus->dispatch(new self($this->buildId));
        }
    }

    public function failed(?Throwable $failure): void
    {
        app(FeedBuilder::class)->fail($this->buildId, (string) $failure?->getMessage());
    }
}
