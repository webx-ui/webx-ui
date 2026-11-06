<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Help\HelpBuilder;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Localization\Locales;

/**
 * `GET /api/cms/help/{key}`: the help of one screen in the language of the panel, asked for when
 * somebody presses «?». The texts never travel with the manifest: a session opens help on a screen
 * or two (WEBX_UI_PANEL_HELP.md §3.1).
 *
 * A broken file is not answered around: like a broken screen it is a bug in a package, and it
 * says which file.
 */
final class HelpController
{
    public function __invoke(Request $request, HelpBuilder $help, Locales $locales, string $key): JsonResponse
    {
        $user = $request->user();

        $page = $help->build(
            $key,
            $locales->resolvePanel($locales->current()),
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
            static fn (string $words): string => (string) __($words),
        );

        if ($page === null) {
            return ApiResponse::message((string) __('webx-admin::screens.unknown', ['name' => $key]), 404);
        }

        return ApiResponse::data($page);
    }
}
