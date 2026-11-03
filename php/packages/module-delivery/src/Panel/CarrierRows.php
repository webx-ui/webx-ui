<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel;

use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\NamesSkipReasons;
use WebxUi\Delivery\Contracts\SyncsDirectories;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Settings\SettingOptions;
use WebxUi\Delivery\Sync\Directories;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\DirectorySchedule;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Geography\Matching\BindingStatus;

/**
 * A carrier as the screen of the carriers and the agent's `delivery_sync_status` see it (§14.2,
 * §15): its directories with the last success, a run going on, a pass held at the threshold,
 * stale or not, how many records, how many wait in the binding queue, and the remarks of the last
 * pass. One class for both doors, so that the panel and an agent read the same state.
 */
final class CarrierRows
{
    public function __construct(
        private readonly Directories $directories,
        private readonly SettingOptions $options,
    ) {}

    /** @return array<string, mixed> */
    public function summary(Carrier $carrier, string $locale): array
    {
        $key = $carrier->key();
        $directories = [];

        if ($carrier instanceof SyncsDirectories) {
            foreach ($this->directories->schedules($key) as $schedule) {
                $directories[] = $this->directory($carrier, $schedule, $locale);
            }
        }

        return [
            'key' => $key,
            'title' => $carrier->title($locale),
            'icon' => $carrier->icon(),
            'enabled' => $this->directories->enabled($key),
            'countries' => $carrier->countries(),
            'syncs' => $carrier instanceof SyncsDirectories,
            'directories' => $directories,
            // Settings the account no longer has, from the options last asked for: a list asks no carrier.
            'missing' => array_values($this->missing($carrier)),
            'queue' => DeliveryCarrierLocality::query()
                ->where('carrier', $key)
                ->whereNull('removed_at')
                ->where('status', BindingStatus::Pending->value)
                ->count(),
        ];
    }

    /** @return array<string, string> field → label */
    public function missing(Carrier $carrier, bool $ask = false): array
    {
        if (SettingOptions::fields($carrier) === []) {
            return [];
        }

        $row = DeliveryCarrier::query()->find($carrier->key());

        return $this->options->missing($carrier, $row->settings ?? [], $row->credentials ?? [], $ask);
    }

    /** @return array<string, mixed> */
    public function directory(Carrier $one, DirectorySchedule $schedule, string $locale): array
    {
        $carrier = $one->key();
        $directory = $schedule->directory;
        $latest = $this->directories->latest($carrier, $directory);
        $success = $this->directories->lastSuccess($carrier, $directory);
        $remarks = [];

        // «Localities with a courier and no streets: N» — from the last pass of the streets.
        if ($directory === Directory::Streets && is_array($success?->warnings[Synchronizer::NO_STREETS] ?? null)) {
            $count = (int) ($success->warnings[Synchronizer::NO_STREETS]['count'] ?? 0);
            $remarks[] = ['key' => Synchronizer::NO_STREETS, 'count' => $count, 'label' => self::remark(Synchronizer::NO_STREETS, $count, $locale)];
        }

        if (is_array($success?->warnings['skipped'] ?? null)) {
            foreach ($success->warnings['skipped'] as $reason => $skipped) {
                $count = (int) (is_array($skipped) ? ($skipped['count'] ?? 0) : $skipped);
                $remarks[] = [
                    'key' => 'skipped.'.$reason,
                    'count' => $count,
                    // The words come with the row: a satellite's reasons are in its own dictionary,
                    // which the panel's does not carry.
                    'label' => ($one instanceof NamesSkipReasons ? $one->skipReason((string) $reason, $count, $locale) : null)
                        ?? self::remark('skipped-'.$reason, $count, $locale)
                        ?? self::remark('skipped-other', $count, $locale)
                        ?? (string) $count,
                ];
            }
        }

        return [
            'directory' => $directory->value,
            'cron' => $this->directories->cron($carrier, $schedule),
            'last_success' => $success?->finished_at?->toIso8601String(),
            'latest' => $latest === null ? null : self::run($latest),
            'running' => $latest?->isRunning() ?? false,
            'held' => $latest !== null && $latest->status === RunStatus::Held ? $latest->held() : null,
            'stale' => $this->directories->stale($carrier, $schedule),
            'records' => $this->records($carrier, $directory),
            'remarks' => $remarks,
        ];
    }

    /** A remark in the base's words, or null when the base has none under that key. */
    private static function remark(string $key, int $count, string $locale): ?string
    {
        $path = 'webx-delivery::panel.carrier.remarks.'.$key;
        $line = trans($path, ['count' => $count], $locale);

        return is_string($line) && $line !== $path ? $line : null;
    }

    public function records(string $carrier, Directory $directory): int
    {
        return match ($directory) {
            Directory::Localities => DeliveryCarrierLocality::query()->where('carrier', $carrier)->whereNull('removed_at')->count(),
            Directory::Points => DeliveryPoint::query()->where('carrier', $carrier)->whereNull('removed_at')->count(),
            Directory::Streets => (int) DeliveryCarrierLocality::query()->where('carrier', $carrier)->whereNull('removed_at')->sum('streets'),
        };
    }

    /** @return array<string, mixed> */
    public static function run(DeliverySyncRun $run): array
    {
        return [
            'id' => $run->id,
            'directory' => $run->directory->value,
            'status' => $run->status->value,
            'trigger' => $run->trigger->value,
            'pages' => $run->pages,
            'seen' => $run->seen,
            'created' => $run->created,
            'updated' => $run->updated,
            'removed' => $run->removed,
            'queued' => $run->queued,
            'held' => $run->status === RunStatus::Held ? $run->held() : null,
            'error' => $run->error,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
        ];
    }
}
