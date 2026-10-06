<?php

declare(strict_types=1);

namespace WebxUi\Settings\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Screens\ScreenRegistry;
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
        return ApiResponse::data(['values' => $this->described($settings, Settings::SCREEN)]);
    }

    /** The house rules for content, on their own: the page where agents are connected edits them. */
    public function content(Settings $settings): JsonResponse
    {
        return ApiResponse::data(['values' => $this->described($settings, Settings::CONTENT_SCREEN)]);
    }

    public function updateContent(Request $request, Settings $settings, ScreenValues $values): JsonResponse
    {
        return $this->write($request, $settings, $values, Settings::CONTENT_SCREEN);
    }

    public function update(Request $request, Settings $settings, ScreenValues $values): JsonResponse
    {
        return $this->write($request, $settings, $values, Settings::SCREEN);
    }

    private function write(Request $request, Settings $settings, ScreenValues $values, string $screen): JsonResponse
    {
        $input = $request->input('values');
        $user = $request->user();

        $stored = $values->validate(
            $screen,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
        );

        $settings->save($stored);

        return ApiResponse::data(['values' => $this->described($settings, $screen)]);
    }

    /**
     * Only the keys the screen has: a row left behind by a field that was removed is not
     * something the form should carry around.
     *
     * @return array<string, mixed>
     */
    private function described(Settings $settings, string $screen): array
    {
        $keys = array_column(app(ScreenRegistry::class)->fields($screen), 'name');

        return array_intersect_key($settings->raw(), array_flip($keys));
    }
}
