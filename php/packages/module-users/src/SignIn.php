<?php

declare(strict_types=1);

namespace WebxUi\Users;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use WebxUi\Localization\Locales;
use WebxUi\Users\Models\User;

/**
 * Signing a visitor in and out on the guard `site` (spec §1, §6).
 *
 * The session is one for both guards of the site, so the visitor's leaving must not end the
 * administrator's: the guard's own logout and a new CSRF token, never `session()->invalidate()`,
 * which would throw the `cms` guard out with it. Signing in moves the session to a new id
 * (the guard's `login()`) and keeps what was in it — a guest's cart, the page to go back to.
 */
final readonly class SignIn
{
    public function __construct(
        private Auth $auth,
        private Locales $locales,
    ) {}

    public static function registrationOpen(): bool
    {
        return config('webx-users.registration', 'open') !== 'closed';
    }

    public function in(Request $request, User $user, bool $remember = false): void
    {
        $this->auth->guard(Urls::guard())->login($user, $remember);

        // Last sign-in and the language of the letters: no event — they are not an edit.
        User::query()->whereKey($user->getKey())->update([
            'last_sign_in_at' => Date::now(),
            'locale' => $this->locales->current(),
        ]);
    }

    /** @return int|null the visitor who left, or null when nobody was signed in */
    public function out(Request $request): ?int
    {
        $guard = $this->auth->guard(Urls::guard());
        $user = $guard->user();

        if (! $user instanceof User) {
            return null;
        }

        $guard->logout();

        if ($request->hasSession()) {
            $request->session()->regenerateToken();
        }

        return (int) $user->getKey();
    }
}
