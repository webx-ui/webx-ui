<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Http\Resources\CmsUserPayload;
use WebxUi\Auth\Models\CmsUser;

/**
 * Change the theme this administrator reads the panel in.
 *
 * Stored on the person rather than in the browser, for the same reason the language is: the
 * choice is about them, not about the machine they happen to be sitting at, and somebody who
 * works dark at night on a laptop should not have to say so again in the morning at a desk.
 */
final class PanelThemeController
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Null is "follow the machine" — a real answer, and the only way to say it, since
            // what the machine says is not ours to store. `present` so that a request which
            // forgot the field is a mistake rather than a silent reset.
            'theme' => ['present', 'nullable', Rule::in(['light', 'dark'])],
        ]);

        /** @var CmsUser $user */
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        $user->forceFill(['theme' => $validated['theme']])->save();

        return ApiResponse::data(CmsUserPayload::make($user));
    }
}
