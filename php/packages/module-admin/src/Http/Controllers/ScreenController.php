<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Screens\ScreenRegistry;

/**
 * `GET /api/cms/screens/{name}`: one screen, ready to draw — patched, cut down to what this
 * administrator may see, and translated into the language they read the panel in.
 */
final class ScreenController
{
    public function __invoke(Request $request, ScreenRegistry $screens, string $name): JsonResponse
    {
        if (! $screens->has($name)) {
            return ApiResponse::message((string) __('webx-admin::screens.unknown', ['name' => $name]), 404);
        }

        $user = $request->user();

        return ApiResponse::data([
            'screen' => $name,
            'root' => $screens->render(
                $name,
                static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
                static fn (string $key): string => (string) __($key),
            ),
        ]);
    }
}
