<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Panel\CarrierRows;
use WebxUi\Delivery\Settings\SettingOption;
use WebxUi\Delivery\Settings\SettingOptions;
use WebxUi\Delivery\Settings\SettingOptionsRefused;
use WebxUi\Delivery\Settings\SettingsRejected;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\Directories;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\ManualSync;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\SyncAlreadyRunning;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
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
        private readonly CarrierRows $rows,
        private readonly SettingOptions $options,
    ) {}

    public function index(): JsonResponse
    {
        $rows = [];

        foreach ($this->carriers->all() as $carrier) {
            if ($carrier->key() !== Carriers::STORE) {
                $rows[] = $this->rows->summary($carrier, $this->locales->current());
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
            ...$this->rows->summary($one, $this->locales->current()),
            'settings' => [
                'fields' => $one->settings(),
                'values' => $row->settings ?? [],
                // Chosen values the carrier's account no longer has: field → label (§5.4).
                'missing' => (object) $this->options->missing($one, $row->settings ?? [], $stored),
            ],
            'credentials' => array_map(static fn (array $field): array => [
                ...$field,
                // «Set» or not; the value itself never leaves the server (§13.2).
                'set' => isset($field['name']) && ($stored[$field['name']] ?? null) !== null && ($stored[$field['name']] ?? '') !== '',
            ], $one->credentials()),
            'runs' => DeliverySyncRun::query()->where('carrier', $carrier)->orderByDesc('id')->limit(30)->get()
                ->map(static fn (DeliverySyncRun $run): array => CarrierRows::run($run))
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

        // After the keys: a key typed in the same save is the one the options are asked with.
        if (is_array($request->input('settings'))) {
            $names = array_filter(array_column($one->settings(), 'name'), 'is_string');
            $before = $row->settings ?? [];
            $after = [...$before, ...array_intersect_key($request->input('settings'), array_flip($names))];

            try {
                // A value from the carrier's account is taken only if the account has it (§5.4).
                $row->settings = $this->options->check($one, $before, $after, $row->credentials ?? []);
            } catch (SettingsRejected $rejected) {
                $errors = [];

                foreach ($rejected->errors as $field => $words) {
                    $errors['settings.'.$field] = [$words];
                }

                throw ValidationException::withMessages($errors);
            }
        }

        $row->save();

        return $this->show($carrier);
    }

    /**
     * The options of a field from the carrier's account (§5.2): the values of what it depends on
     * in `values`, what was typed in `q`, and `fresh` for «Refresh lists». The keys are the stored
     * ones — none travels in the request.
     */
    public function options(Request $request, string $carrier, string $field): JsonResponse
    {
        $one = $this->carrier($carrier);
        $values = $request->query('values');
        $query = $request->query('q');

        try {
            $options = $this->options->options(
                $one,
                $field,
                is_array($values) ? $values : [],
                DeliveryCarrier::query()->find($carrier)->credentials ?? [],
                is_string($query) ? $query : null,
                $request->boolean('fresh'),
            );
        } catch (SettingOptionsRefused $refused) {
            return ApiResponse::message($refused->getMessage(), $refused->status);
        } catch (CarrierUnavailable $failure) {
            // 422: the panel shows the words of a refusal it wrote itself, and a 5xx only as «server error».
            return ApiResponse::message(SettingOptions::unavailable($failure), 422);
        }

        return ApiResponse::data(array_map(static fn (SettingOption $option): array => $option->toArray(), $options));
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
    public function sync(string $carrier, string $directory, ManualSync $manual): JsonResponse
    {
        $this->carrier($carrier);
        $first = Directory::tryFrom($directory) ?? abort(404);

        abort_unless(isset($this->directories->schedules($carrier)[$first->value]), 404);

        try {
            $list = $manual->plan($carrier, $first);
        } catch (SyncAlreadyRunning $running) {
            return ApiResponse::message($running->getMessage(), 409);
        } catch (DeliveryException $refusal) {
            throw ValidationException::withMessages(['carrier' => [$refusal->getMessage()]]);
        }

        $manual->start($carrier, $list, Trigger::Panel);

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

        return ApiResponse::data(CarrierRows::run($run->refresh()));
    }

    private function carrier(string $key): Carrier
    {
        abort_if($key === Carriers::STORE || ! $this->carriers->has($key), 404);

        return $this->carriers->get($key);
    }
}
