<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use WebxUi\CatalogLandings\Catalog\LandingGenerator;

/**
 * One chunk of a generation, and the next one queued behind it (§8.3 of the landings spec) — a
 * job per chunk, as the core's bulk actions: a deploy or a worker's time limit cuts a run of
 * thousands of landings between chunks, not in the middle of one.
 */
final class GenerateLandings implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $runId) {}

    public function handle(LandingGenerator $generator, Dispatcher $bus): void
    {
        if ($generator->chunk($this->runId)) {
            $bus->dispatch(new self($this->runId));
        }
    }

    public function failed(?Throwable $failure): void
    {
        app(LandingGenerator::class)->fail($this->runId);
    }
}
