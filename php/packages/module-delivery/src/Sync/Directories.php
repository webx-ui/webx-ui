<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use Cron\CronExpression;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\SyncsDirectories;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliverySyncRun;

/**
 * Which carriers keep directories on the site, when each directory runs, and whether its last
 * success is too old (§7.6–§7.7). What the schedule, the command, `webx:doctor`, and later the
 * carriers screen and `delivery_sync_status` read alike.
 */
final class Directories
{
    /** The schedule override that turns a directory's own runs off: the panel's «manually». */
    public const string MANUAL = 'manual';

    /** A last success older than this many intervals of the schedule is stale (§7.6). */
    public const int STALE_AFTER_INTERVALS = 3;

    public function __construct(
        private readonly Carriers $carriers,
        private readonly Config $config,
    ) {}

    /**
     * The registered carriers that sync, by key — on or off.
     *
     * @return array<string, Carrier&SyncsDirectories>
     */
    public function carriers(): array
    {
        $syncing = [];

        foreach ($this->carriers->all() as $carrier) {
            if ($carrier instanceof SyncsDirectories) {
                $syncing[$carrier->key()] = $carrier;
            }
        }

        return $syncing;
    }

    /** @return Carrier&SyncsDirectories */
    public function carrier(string $key): SyncsDirectories
    {
        $carrier = $this->carriers->get($key);

        if (! $carrier instanceof SyncsDirectories) {
            throw new DeliveryException("Delivery carrier {$key} keeps no directories on the site.");
        }

        return $carrier;
    }

    /** Whether the carrier has its row and the row is on: a carrier without one is off (§5). */
    public function enabled(string $key): bool
    {
        return (bool) DeliveryCarrier::query()->whereKey($key)->value('enabled');
    }

    /**
     * The carrier's directories in the order a night pulls them: localities, then points.
     *
     * @return array<string, DirectorySchedule> by directory
     */
    public function schedules(string $key): array
    {
        $schedules = [];

        foreach ($this->carrier($key)->directories() as $schedule) {
            if ($schedule instanceof DirectorySchedule) {
                $schedules[$schedule->directory->value] = $schedule;
            }
        }

        $ordered = [];

        foreach (Directory::cases() as $directory) {
            if (isset($schedules[$directory->value])) {
                $ordered[$directory->value] = $schedules[$directory->value];
            }
        }

        return $ordered;
    }

    public function schedule(string $key, Directory $directory): DirectorySchedule
    {
        return $this->schedules($key)[$directory->value]
            ?? throw new DeliveryException("Delivery carrier {$key} has no {$directory->value} directory.");
    }

    /**
     * The cron the directory runs by: the site's `webx-delivery.carriers.<key>.schedule.<directory>`
     * over the satellite's; null — run by hand only.
     */
    public function cron(string $key, DirectorySchedule $schedule): ?string
    {
        $override = $this->config->get("webx-delivery.carriers.{$key}.schedule.{$schedule->directory->value}");
        $cron = is_string($override) && trim($override) !== '' ? trim($override) : $schedule->cron;

        if ($cron === self::MANUAL || ! CronExpression::isValidExpression($cron)) {
            return null;
        }

        return $cron;
    }

    /** Seconds between two runs of the cron, as it stands now. */
    public function interval(string $cron, ?DateTimeImmutable $now = null): int
    {
        try {
            $expression = new CronExpression($cron);
            $next = $expression->getNextRunDate($now ?? new DateTimeImmutable);
            $after = $expression->getNextRunDate($next);

            return max(60, $after->getTimestamp() - $next->getTimestamp());
        } catch (Throwable) {
            return 86400;
        }
    }

    public function latest(string $key, Directory $directory): ?DeliverySyncRun
    {
        return DeliverySyncRun::query()->of($key, $directory)->orderByDesc('id')->first();
    }

    /**
     * The last run that left the directory complete: succeeded, or held — a held pass wrote
     * everything it got and only did not remove (§7.5).
     */
    public function lastSuccess(string $key, Directory $directory): ?DeliverySyncRun
    {
        return DeliverySyncRun::query()
            ->of($key, $directory)
            ->whereIn('status', [RunStatus::Succeeded->value, RunStatus::Held->value])
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Whether the directory is stale: scheduled, and its last success older than three intervals
     * of the schedule — or none at all. A directory run by hand is never stale.
     */
    public function stale(string $key, DirectorySchedule $schedule, ?Carbon $now = null): bool
    {
        $cron = $this->cron($key, $schedule);

        if ($cron === null) {
            return false;
        }

        $now ??= Carbon::now();
        $success = $this->lastSuccess($key, $schedule->directory);

        if ($success?->finished_at === null) {
            return true;
        }

        return $success->finished_at->getTimestamp() < $now->getTimestamp() - self::STALE_AFTER_INTERVALS * $this->interval($cron, $now->toDateTimeImmutable());
    }
}
