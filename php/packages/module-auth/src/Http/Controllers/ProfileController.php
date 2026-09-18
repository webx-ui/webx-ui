<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Http\Requests\ProfileRequest;
use WebxUi\Auth\Http\Resources\CmsUserPayload;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Localization\Locales;

/**
 * Whoever is signed in, editing themselves.
 *
 * Separate from the administrators screen, and not behind `admins.manage`: changing your own
 * name, your own photograph and your own password is not managing anybody, and a panel where
 * an editor has to ask somebody else to fix the spelling of their name is a panel nobody fixes
 * the spelling of their name in.
 *
 * What it does not touch is exactly what makes it safe to leave open to everyone — roles,
 * super, active, and the address this person signs in with. Those are `AdminController`'s.
 *
 * The answer is the same payload `me` returns, so the panel adopts it the same way.
 */
final class ProfileController
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Locales $locales,
    ) {}

    public function __invoke(ProfileRequest $request): JsonResponse
    {
        /** @var CmsUser $user */
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        $user->name = (string) $request->string('name');
        $user->avatar = $request->input('avatar') === null ? null : (string) $request->string('avatar');

        // Only a language the panel actually has, and `null` to go back to following the
        // browser — the same rule the language picker in the corner keeps.
        if ($request->has('locale')) {
            $locale = $request->input('locale');
            $codes = array_column($this->locales->panel(), 'code');

            $user->locale = is_string($locale) && in_array($locale, $codes, true) ? $locale : null;
        }

        // Blank is an edit of everything else, not a request to have no password.
        if ($request->filled('password')) {
            $user->password = (string) $request->string('password');
        }

        $user->save();

        return ApiResponse::data(CmsUserPayload::make($user->refresh()));
    }
}
