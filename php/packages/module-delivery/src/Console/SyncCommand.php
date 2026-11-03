<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Sync\Directories;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\SyncAlreadyRunning;
use WebxUi\Delivery\Sync\SyncDirectory;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;

/**
 * `webx:delivery:sync` (§7.7): a carrier's directories now — every carrier that is on when none
 * is named, localities before points. Without `--queue` the run goes on in this process and the
 * command says how it ended; with it the run goes to the queue, as the schedule sends it.
 */
final class SyncCommand extends Command
{
    protected $signature = 'webx:delivery:sync
        {carrier? : The carrier\'s key; every carrier that is on when left out}
        {--directory=* : localities, points, streets — all of the carrier\'s when left out}
        {--trigger=command : Who asked — schedule, panel, mcp or command}
        {--queue : Queue the run instead of doing it here}';

    protected $description = 'Sync the directories of delivery carriers: localities, then points and streets';

    public function handle(Directories $directories, Synchronizer $sync, Dispatcher $bus): int
    {
        $trigger = Trigger::tryFrom((string) $this->option('trigger'));
        $asked = array_values(array_filter((array) $this->option('directory'), is_string(...)));

        if ($trigger === null) {
            $this->components->error('--trigger is one of: '.implode(', ', array_map(static fn (Trigger $trigger): string => $trigger->value, Trigger::cases())).'.');

            return self::FAILURE;
        }

        foreach ($asked as $name) {
            if (Directory::tryFrom($name) === null) {
                $this->components->error("[{$name}] is not a directory: ".implode(', ', Directory::values()).'.');

                return self::FAILURE;
            }
        }

        $key = $this->argument('carrier');

        if (is_string($key)) {
            $carriers = [$key];
        } else {
            $carriers = array_values(array_filter(array_keys($directories->carriers()), $directories->enabled(...)));

            if ($carriers === []) {
                $this->components->info('No carrier that is on keeps directories on the site.');

                return self::SUCCESS;
            }
        }

        $failed = false;

        foreach ($carriers as $carrier) {
            try {
                $list = array_values(array_filter(
                    array_keys($directories->schedules($carrier)),
                    static fn (string $directory): bool => $asked === [] || in_array($directory, $asked, true),
                ));
            } catch (DeliveryException $refusal) {
                $this->components->error($refusal->getMessage());
                $failed = true;

                continue;
            }

            if ($list === []) {
                $this->components->warn("{$carrier}: none of these directories.");

                continue;
            }

            $failed = ($this->option('queue')
                ? ! $this->queue($sync, $bus, $carrier, $list, $trigger)
                : ! $this->here($sync, $carrier, $list, $trigger)) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @param  list<string>  $directories */
    private function queue(Synchronizer $sync, Dispatcher $bus, string $carrier, array $directories, Trigger $trigger): bool
    {
        $running = $sync->running($carrier, Directory::from($directories[0]));

        if ($running !== null) {
            $this->components->warn("{$carrier} {$directories[0]}: ".(new SyncAlreadyRunning($running))->getMessage());

            return true;
        }

        $bus->dispatch(new SyncDirectory($carrier, $directories, $trigger->value));
        $this->components->info("{$carrier}: ".implode(', ', $directories).' queued.');

        return true;
    }

    /** @param  list<string>  $directories */
    private function here(Synchronizer $sync, string $carrier, array $directories, Trigger $trigger): bool
    {
        $ok = true;

        foreach ($directories as $index => $name) {
            try {
                $run = $sync->run($carrier, Directory::from($name), $trigger);
            } catch (SyncAlreadyRunning $running) {
                $this->components->warn("{$carrier} {$name}: {$running->getMessage()}");

                return true;
            } catch (DeliveryException $refusal) {
                $this->components->error("{$carrier} {$name}: {$refusal->getMessage()}");

                return false;
            }

            $this->say($run);

            $ok = $ok && $run->status !== RunStatus::Failed;

            // Points and streets stand on the localities: only a pass of these stops the rest.
            if ($run->status !== RunStatus::Succeeded && $name === Directory::Localities->value) {
                $rest = array_slice($directories, $index + 1);

                if ($rest !== []) {
                    $this->components->warn("{$carrier}: ".implode(', ', $rest).' not synced — they follow a successful '.$name.'.');
                }

                return $ok;
            }
        }

        return $ok;
    }

    private function say(DeliverySyncRun $run): void
    {
        $line = sprintf(
            '%s %s: %s — %d %s, %d seen, %d new, %d updated, %d removed',
            $run->carrier,
            $run->directory->value,
            $run->status->value,
            $run->pages,
            $run->pages === 1 ? 'page' : 'pages',
            $run->seen,
            $run->created,
            $run->updated,
            $run->removed,
        );

        if ($run->queued > 0) {
            $line .= ", {$run->queued} waiting for a binding";
        }

        foreach ($run->warnings['skipped'] ?? [] as $why => $skipped) {
            $line .= sprintf(', %d skipped (%s)', (int) ($skipped['count'] ?? 0), str_replace('_', ' ', (string) $why));
        }

        match ($run->status) {
            RunStatus::Succeeded => $this->components->info($line.'.'),
            RunStatus::Held => $this->components->warn($line.'. Held: the carrier returned '.$run->held().' fewer records than the site has — nothing removed; confirm the removal on the carriers screen.'),
            default => $this->components->error($line.'. '.$run->error),
        };
    }
}
