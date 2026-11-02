<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Doctor;

use Illuminate\Support\Carbon;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Delivery\Sync\Directories;
use WebxUi\Delivery\Sync\RunStatus;

/**
 * The carriers' directories in `webx:doctor` (§7.6): one too old — the last success older than
 * three intervals of its schedule — and a pass held at the removal threshold. The checkout keeps
 * working on the last complete directory either way, and both are mended from the carriers
 * screen, so they are warnings about the content, not reasons to stop a deploy.
 *
 * Registered only where the panel's frame is installed: delivery does not need it.
 */
final class SyncCheck implements Check
{
    private const string SUBJECT = 'Delivery sync';

    public function __construct(private readonly Directories $directories) {}

    public function run(): array
    {
        $found = [];
        $fresh = 0;
        $now = Carbon::now();

        foreach (array_keys($this->directories->carriers()) as $carrier) {
            if (! $this->directories->enabled($carrier)) {
                continue;
            }

            foreach ($this->directories->schedules($carrier) as $name => $schedule) {
                $cron = $this->directories->cron($carrier, $schedule);

                if ($cron === null) {
                    continue;
                }

                $latest = $this->directories->latest($carrier, $schedule->directory);

                if ($latest?->status === RunStatus::Held) {
                    $found[] = Diagnosis::content(self::SUBJECT, "{$carrier} {$name}: the last pass is held — the carrier returned {$latest->held()} fewer records than the site has, and nothing was removed. Confirm the removal on the carriers screen if that is right.");
                }

                if (! $this->directories->stale($carrier, $schedule, $now)) {
                    $fresh++;

                    continue;
                }

                $success = $this->directories->lastSuccess($carrier, $schedule->directory)?->finished_at;
                $since = $success === null ? 'has never synced' : 'last synced '.$success->diffForHumans($now);
                $found[] = Diagnosis::content(self::SUBJECT, "{$carrier} {$name} {$since} (schedule {$cron}); the checkout works on the last complete directory. Look at the runs on the carriers screen, or run php artisan webx:delivery:sync {$carrier}.");
            }
        }

        if ($found !== [] || $fresh === 0) {
            return $found;
        }

        return [Diagnosis::ok(self::SUBJECT, sprintf('%d %s up to date.', $fresh, $fresh === 1 ? 'directory' : 'directories'))];
    }
}
