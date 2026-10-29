<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Orders\OrderSettings;
use WebxUi\Orders\Panel\SettingsScreen;

/**
 * `/api/cms/orders/settings` (spec §11.4), behind `orders.statuses`: the format of the number with
 * its trial number, and the statuses a new order comes into. Checked against the screen
 * `orders.settings` and kept where every setting of the site is; on a site without
 * `module-settings` the screen says the config decides, and a save finds nothing to keep.
 */
final readonly class SettingsController
{
    public function __construct(
        private OrderSettings $settings,
        private ScreenValues $values,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::data($this->settings->describe());
    }

    /** `dry_run` checks the values and answers the trial number; nothing is kept. */
    public function update(Request $request): JsonResponse
    {
        if ($this->settings->stored() === null) {
            abort(404);
        }

        $input = $request->input('values');
        $user = $request->user();
        $valid = $this->values->validate(
            SettingsScreen::NAME,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
        );

        if ($request->boolean('dry_run')) {
            $this->settings->check($valid);

            return ApiResponse::data([...$this->settings->describe(), 'sample' => $this->settings->sample($valid)]);
        }

        $this->settings->store($valid);

        return ApiResponse::data($this->settings->describe());
    }
}
