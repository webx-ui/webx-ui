<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Http\Resources\CmsUserPayload;
use WebxUi\Auth\Models\CmsUser;

/**
 * Switch the panel on the site on or off for this administrator (WEBX_UI_SITE_BAR.md, §3).
 *
 * Theirs alone, like the theme: somebody who reviews the site with a client looking over their
 * shoulder wants the pages clean, and that is no reason to take the bar from their colleagues.
 */
final class PanelSiteBarController
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        /** @var CmsUser $user */
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        $user->forceFill(['site_bar' => (bool) $validated['enabled']])->save();

        return ApiResponse::data(CmsUserPayload::make($user));
    }
}
