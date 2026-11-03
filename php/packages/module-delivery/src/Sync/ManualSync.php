<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use Illuminate\Contracts\Bus\Dispatcher;
use WebxUi\Delivery\DeliveryException;

/**
 * «Run» of a carrier's directory by hand (§7.7) — the panel's button and `delivery_sync_run`:
 * the localities bring the points and the streets after them; one directory of points or streets
 * runs alone. The pass goes to the queue, as the schedule sends it. A run of the first directory
 * going on is {@see SyncAlreadyRunning}; a carrier switched off runs nothing.
 */
final class ManualSync
{
    public function __construct(
        private readonly Directories $directories,
        private readonly Synchronizer $sync,
        private readonly Dispatcher $bus,
    ) {}

    /**
     * The directories a run started at this one would pass, in order; null — the carrier's first.
     *
     * @return list<string>
     *
     * @throws SyncAlreadyRunning
     * @throws DeliveryException the carrier keeps no such directory, or is switched off
     */
    public function plan(string $carrier, ?Directory $first = null): array
    {
        $schedules = $this->directories->schedules($carrier);

        if ($schedules === []) {
            throw new DeliveryException("Delivery carrier {$carrier} keeps no directories on the site.");
        }

        $first ??= Directory::from((string) array_key_first($schedules));

        if (! isset($schedules[$first->value])) {
            throw new DeliveryException(sprintf(
                'Delivery carrier %s has no %s directory. It has: %s.',
                $carrier,
                $first->value,
                implode(', ', array_keys($schedules)),
            ));
        }

        $running = $this->sync->running($carrier, $first);

        if ($running !== null) {
            throw new SyncAlreadyRunning($running);
        }

        if (! $this->directories->enabled($carrier)) {
            throw new DeliveryException((string) __('webx-delivery::panel.errors.carrier_off'));
        }

        $list = $first === Directory::Localities ? array_keys($schedules) : [$first->value];

        return [$first->value, ...array_values(array_diff($list, [$first->value]))];
    }

    /** @param  list<string>  $directories  as {@see self::plan()} gave them */
    public function start(string $carrier, array $directories, Trigger $trigger): void
    {
        $this->bus->dispatch(new SyncDirectory($carrier, $directories, $trigger->value));
    }
}
