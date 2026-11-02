<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Models\DeliverySyncRun;

/**
 * A carrier's directories through the queue (§7.6–§7.7), one stretch at a time.
 *
 * Without a run, the job opens one for the first directory and queues the work behind it. With a
 * run, it writes pages for `webx-delivery.sync.job_seconds` and queues the rest: a worker's time
 * limit or a deploy cuts a run between pages, never inside one. A page the carrier failed throws,
 * and the queue tries it again from the same cursor after 60, 300, 900 seconds; the last failure
 * closes the run in the journal. When a directory succeeds, the next one of the list follows —
 * the points of a night after its localities.
 */
final class SyncDirectory implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $directories  this one first, then those that follow it
     */
    public function __construct(
        public readonly string $carrier,
        public readonly array $directories,
        public readonly string $trigger = 'command',
        public readonly ?int $runId = null,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        $backoff = app(Config::class)->get('webx-delivery.sync.backoff', [60, 300, 900]);

        return array_values(array_map('intval', is_array($backoff) ? $backoff : [60, 300, 900]));
    }

    public function handle(Synchronizer $sync, Dispatcher $bus, Config $config): void
    {
        $directory = Directory::tryFrom($this->directories[0] ?? '');

        if ($directory === null) {
            return;
        }

        if ($this->runId === null) {
            try {
                $run = $sync->start($this->carrier, $directory, Trigger::tryFrom($this->trigger) ?? Trigger::Command);
            } catch (DeliveryException) {
                // Already running, or the carrier was switched off since: nothing to do.
                return;
            }

            $bus->dispatch(new self($this->carrier, $this->directories, $this->trigger, $run->id));

            return;
        }

        $run = DeliverySyncRun::query()->find($this->runId);

        if (! $run instanceof DeliverySyncRun || ! $run->isRunning()) {
            return;
        }

        try {
            $more = $sync->pages($run, max(1.0, (float) $config->get('webx-delivery.sync.job_seconds', 60)));
        } catch (InvalidRecord $failure) {
            // Trying again gives the same records: the run fails at once.
            $sync->fail($run, $failure);

            return;
        }

        if ($more) {
            $bus->dispatch(new self($this->carrier, $this->directories, $this->trigger, $run->id));

            return;
        }

        $rest = array_slice($this->directories, 1);

        if ($rest !== [] && $run->refresh()->status === RunStatus::Succeeded) {
            $bus->dispatch(new self($this->carrier, array_values($rest), $this->trigger));
        }
    }

    public function failed(?Throwable $failure): void
    {
        $run = $this->runId === null ? null : DeliverySyncRun::query()->find($this->runId);

        if ($run instanceof DeliverySyncRun && $run->isRunning()) {
            app(Synchronizer::class)->fail($run, $failure ?? new CarrierUnavailable('The sync job failed.'));
        }
    }
}
