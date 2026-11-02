<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http\Panel;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\SyncsDirectories;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Sync\Directories;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\DirectorySchedule;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\SyncAlreadyRunning;
use WebxUi\Delivery\Sync\SyncDirectory;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Geography\Matching\BindingStatus;
use WebxUi\Localization\Locales;

/**
 * The carriers (§14.2): each with its directories — the last success, a run going on, a pass held
 * at the threshold, stale or not, how many records, how many wait in the binding queue, and the
 * remarks of the last pass — the buttons «Run» and «Confirm removal», and the settings and keys of
 * one carrier.
 *
 * Keys are never sent back (§13.2): the screen knows only whether each one is set, and a key is
 * replaced by typing a new one. A blank field leaves the stored key as it is.
 */
final class CarrierController
{
    public function __construct(
        private readonly Carriers $carriers,
        private readonly Directories $directories,
        private readonly Locales $locales,
    ) {}

    public function index(): JsonResponse
    {
        $rows = [];

        foreach ($this->carriers->all() as $carrier) {
            if ($carrier->key() !== Carriers::STORE) {
                $rows[] = $this->summary($carrier);
            }
        }

        return ApiResponse::data($rows);
    }

    public function show(string $carrier): JsonResponse
    {
        $one = $this->carrier($carrier);
        $row = DeliveryCarrier::query()->find($carrier);
        $stored = $row->credentials ?? [];

        return ApiResponse::data([
            ...$this->summary($one),
            'settings' => [
                'fields' => $one->settings(),
                'values' => $row->settings ?? [],
            ],
            'credentials' => array_map(static fn (array $field): array => [
                ...$field,
                // «Set» or not; the value itself never leaves the server (§13.2).
                'set' => isset($field['name']) && ($stored[$field['name']] ?? null) !== null && ($stored[$field['name']] ?? '') !== '',
            ], $one->credentials()),
            'runs' => DeliverySyncRun::query()->where('carrier', $carrier)->orderByDesc('id')->limit(30)->get()
                ->map(static fn (DeliverySyncRun $run): array => self::run($run))
                ->values()
                ->all(),
        ]);
    }

    /** On or off, the settings by their names, and the keys typed anew. */
    public function update(Request $request, string $carrier): JsonResponse
    {
        $one = $this->carrier($carrier);
        $row = DeliveryCarrier::query()->firstOrNew(['key' => $carrier]);

        if ($request->has('enabled')) {
            $row->enabled = $request->boolean('enabled');
        }

        if (is_array($request->input('settings'))) {
            $names = array_filter(array_column($one->settings(), 'name'), 'is_string');
            $row->settings = [...($row->settings ?? []), ...array_intersect_key($request->input('settings'), array_flip($names))];
        }

        if (is_array($request->input('credentials')) || is_array($request->input('clear'))) {
            $names = array_filter(array_column($one->credentials(), 'name'), 'is_string');
            $credentials = $row->credentials ?? [];

            foreach ((array) $request->input('credentials', []) as $name => $value) {
                // Blank is «leave it»: the field on screen is empty because the key is never sent.
                if (in_array($name, $names, true) && is_string($value) && trim($value) !== '') {
                    $credentials[$name] = trim($value);
                }
            }

            foreach ((array) $request->input('clear', []) as $name) {
                unset($credentials[$name]);
            }

            $row->credentials = $credentials === [] ? null : $credentials;
        }

        $row->save();

        return $this->show($carrier);
    }

    /** «Check the connection» with the stored keys and any typed over them, not saved. */
    public function test(Request $request, string $carrier): JsonResponse
    {
        $one = $this->carrier($carrier);
        $credentials = DeliveryCarrier::query()->find($carrier)->credentials ?? [];

        foreach ((array) $request->input('credentials', []) as $name => $value) {
            if (is_string($value) && trim($value) !== '') {
                $credentials[$name] = trim($value);
            }
        }

        $result = $one->testConnection($credentials);

        return ApiResponse::data(['ok' => $result->ok, 'message' => $result->message]);
    }

    /**
     * «Run»: the pass goes to the queue, as the schedule sends it; the localities bring the points
     * and the streets after them (§7.7). A run going on is a 409 with its page.
     */
    public function sync(string $carrier, string $directory, Synchronizer $sync, Dispatcher $bus): JsonResponse
    {
        $this->carrier($carrier);
        $first = Directory::tryFrom($directory) ?? abort(404);
        $schedules = $this->directories->schedules($carrier);

        abort_unless(isset($schedules[$first->value]), 404);

        $running = $sync->running($carrier, $first);

        if ($running !== null) {
            return ApiResponse::message((new SyncAlreadyRunning($running))->getMessage(), 409);
        }

        if (! $this->directories->enabled($carrier)) {
            throw ValidationException::withMessages(['carrier' => [__('webx-delivery::panel.errors.carrier_off')]]);
        }

        $list = $first === Directory::Localities ? array_keys($schedules) : [$first->value];
        $list = [$first->value, ...array_values(array_diff($list, [$first->value]))];

        $bus->dispatch(new SyncDirectory($carrier, $list, 'panel'));

        return ApiResponse::data(['queued' => $list], 202);
    }

    /** «Confirm removal» of a pass held at the threshold (§7.5): only the latest run of it. */
    public function confirm(string $carrier, string $directory, Synchronizer $sync): JsonResponse
    {
        $this->carrier($carrier);
        $dir = Directory::tryFrom($directory) ?? abort(404);
        $run = $this->directories->latest($carrier, $dir);

        if ($run === null || $run->status !== RunStatus::Held) {
            throw ValidationException::withMessages(['run' => [__('webx-delivery::panel.errors.not_held')]]);
        }

        try {
            $sync->confirm($run);
        } catch (DeliveryException $refusal) {
            throw ValidationException::withMessages(['run' => [$refusal->getMessage()]]);
        }

        return ApiResponse::data(self::run($run->refresh()));
    }

    /** @return array<string, mixed> */
    private function summary(Carrier $carrier): array
    {
        $key = $carrier->key();
        $directories = [];

        if ($carrier instanceof SyncsDirectories) {
            foreach ($this->directories->schedules($key) as $schedule) {
                $directories[] = $this->directory($key, $schedule);
            }
        }

        return [
            'key' => $key,
            'title' => $carrier->title($this->locales->current()),
            'icon' => $carrier->icon(),
            'enabled' => $this->directories->enabled($key),
            'countries' => $carrier->countries(),
            'syncs' => $carrier instanceof SyncsDirectories,
            'directories' => $directories,
            'queue' => DeliveryCarrierLocality::query()
                ->where('carrier', $key)
                ->whereNull('removed_at')
                ->where('status', BindingStatus::Pending->value)
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function directory(string $carrier, DirectorySchedule $schedule): array
    {
        $directory = $schedule->directory;
        $latest = $this->directories->latest($carrier, $directory);
        $success = $this->directories->lastSuccess($carrier, $directory);
        $remarks = [];

        // «Localities with a courier and no streets: N» — from the last pass of the streets.
        if ($directory === Directory::Streets && is_array($success?->warnings[Synchronizer::NO_STREETS] ?? null)) {
            $remarks[] = ['key' => Synchronizer::NO_STREETS, 'count' => (int) ($success->warnings[Synchronizer::NO_STREETS]['count'] ?? 0)];
        }

        if (is_array($success?->warnings['skipped'] ?? null)) {
            foreach ($success->warnings['skipped'] as $reason => $skipped) {
                $remarks[] = ['key' => 'skipped.'.$reason, 'count' => (int) (is_array($skipped) ? ($skipped['count'] ?? 0) : $skipped)];
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

    private function records(string $carrier, Directory $directory): int
    {
        return match ($directory) {
            Directory::Localities => DeliveryCarrierLocality::query()->where('carrier', $carrier)->whereNull('removed_at')->count(),
            Directory::Points => DeliveryPoint::query()->where('carrier', $carrier)->whereNull('removed_at')->count(),
            Directory::Streets => (int) DeliveryCarrierLocality::query()->where('carrier', $carrier)->whereNull('removed_at')->sum('streets'),
        };
    }

    /** @return array<string, mixed> */
    private static function run(DeliverySyncRun $run): array
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

    private function carrier(string $key): Carrier
    {
        abort_if($key === Carriers::STORE || ! $this->carriers->has($key), 404);

        return $this->carriers->get($key);
    }
}
