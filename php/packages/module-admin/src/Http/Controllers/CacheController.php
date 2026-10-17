<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use WebxUi\Admin\Cache\CacheModule;
use WebxUi\Admin\Cache\CacheReset;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;

/**
 * «System → Cache» (WEBX_UI_CACHE.md §5): what is cached where, and the reset. The frame has no
 * `cms.can` of its own — that middleware is the auth module's — so the permission is asked here.
 */
final class CacheController
{
    public function __construct(private readonly CacheReset $reset) {}

    public function show(Request $request): JsonResponse
    {
        $this->allowed($request, CacheModule::VIEW, CacheModule::RESET);

        return ApiResponse::data($this->reset->report());
    }

    /** One group from the body, or every group without one; answers with the page's data as it now is. */
    public function reset(Request $request): JsonResponse
    {
        $this->allowed($request, CacheModule::RESET);

        $group = $request->input('group');
        $asked = is_string($group) && trim($group) !== '' ? [trim($group)] : [];

        try {
            $moved = $this->reset->reset($asked);
        } catch (InvalidArgumentException $failure) {
            throw ValidationException::withMessages(['group' => $failure->getMessage()]);
        }

        return ApiResponse::data(['reset' => $moved] + $this->reset->report());
    }

    private function allowed(Request $request, string ...$permissions): void
    {
        $user = $request->user();

        if (! $user instanceof HasPermissions) {
            throw new AccessDeniedHttpException((string) __('webx-admin::cache.forbidden'));
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        throw new AccessDeniedHttpException((string) __('webx-admin::cache.forbidden'));
    }
}
