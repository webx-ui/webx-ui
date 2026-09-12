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
use WebxUi\Localization\Locales;

/**
 * Change the language this administrator reads the panel in.
 *
 * Stored on the person rather than in the browser, so the choice follows them to the next
 * machine and so the server can answer their validation messages in it.
 */
final class PanelLocaleController
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Locales $locales,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Only a language the panel has actually been translated into: storing anything
            // else would put somebody in an interface that falls back on every single line.
            'locale' => ['required', 'string', Rule::in(array_column($this->locales->panel(), 'code'))],
        ]);

        /** @var CmsUser $user */
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        $user->forceFill(['locale' => $validated['locale']])->save();

        return ApiResponse::data(CmsUserPayload::make($user));
    }
}
