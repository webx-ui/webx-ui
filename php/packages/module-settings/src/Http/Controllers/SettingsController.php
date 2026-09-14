<?php

declare(strict_types=1);

namespace WebxUi\Settings\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Settings\Settings;

/**
 * `GET` the values, `PUT` them back. Saving goes by the screen: what the tree does not name
 * is dropped, what the administrator may not see is not written, and a 422 lands under the
 * field it is about.
 */
final class SettingsController
{
    public function index(Settings $settings): JsonResponse
    {
        return ApiResponse::data(['values' => $this->described($settings)]);
    }

    public function update(Request $request, Settings $settings, ScreenValues $values): JsonResponse
    {
        $input = $request->input('values');
        $user = $request->user();

        $stored = $values->validate(
            Settings::SCREEN,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
        );

        $settings->save($stored);

        return ApiResponse::data(['values' => $this->described($settings)]);
    }

    /**
     * Only the keys the screen has: a row left behind by a field that was removed is not
     * something the form should carry around.
     *
     * @return array<string, mixed>
     */
    private function described(Settings $settings): array
    {
        return array_intersect_key($settings->raw(), array_flip($settings->keys()));
    }
}
