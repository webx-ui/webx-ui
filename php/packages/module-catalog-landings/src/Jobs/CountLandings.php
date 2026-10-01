<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use WebxUi\CatalogLandings\Catalog\LandingCounter;

/**
 * The landings waiting for a count, counted (§6.5 of the landings spec). Queued after a batch of
 * the index touched their bases; unique while it waits, so a rebuild's hundreds of batches make
 * one recount rather than hundreds — whatever a later batch marks, the waiting job still reads.
 */
final class CountLandings implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public function handle(LandingCounter $counter): void
    {
        $counter->recount();
    }
}
