<?php

declare(strict_types=1);

namespace WebxUi\Users;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use WebxUi\Localization\Locales;
use WebxUi\Settings\Settings;
use WebxUi\Users\Models\User;

/**
 * Signing a visitor in and out on the guard `site` (spec §1, §6).
 *
 * The session is one for both guards of the site, so the visitor's leaving must not end the
 * administrator's: the guard's own logout of this device and a new CSRF token, never `session()->invalidate()`,
 * which would throw the `cms` guard out with it. Signing in moves the session to a new id
 * (the guard's `login()`) and keeps what was in it — a guest's cart, the page to go back to.
 */
final readonly class SignIn
{
    public function __construct(
        private Auth $auth,
        private Locales $locales,
    ) {}

    /**
     * The setting of the panel (`users.registration`, §16.3) where the site keeps settings and
     * an administrator saved one, the config otherwise.
     */
    public static function registrationOpen(): bool
    {
        $stored = app()->bound(Settings::class) ? app(Settings::class)->raw()['users.registration'] ?? null : null;

        return (is_string($stored) ? $stored : config('webx-users.registration', 'open')) !== 'closed';
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

    /**
     * The remember token changed under a signed-in visitor (a new password, «Sign out everywhere»):
     * the device that changed it stays in — its remember cookie gets the new token, the session
     * a new stamp on the way out (TrackVisitor).
     */
    public function keep(Request $request, User $user): void
    {
        $guard = $this->auth->guard(Urls::guard());

        if ($guard instanceof SessionGuard && $request->cookies->has($guard->getRecallerName())) {
            $guard->login($user, true);
        }
    }

    /** @return int|null the visitor who left, or null when nobody was signed in */
    public function out(Request $request): ?int
    {
        $guard = $this->auth->guard(Urls::guard());
        $user = $guard->user();

        if (! $user instanceof User) {
            return null;
        }

        // This device only: the remember token is the stamp of every session (TrackVisitor), and
        // changing it here would sign the visitor out of all of them. «Sign out everywhere» does.
        if ($guard instanceof SessionGuard) {
            $guard->logoutCurrentDevice();
        } else {
            $guard->logout();
        }

        if ($request->hasSession()) {
            $request->session()->regenerateToken();
        }

        return (int) $user->getKey();
    }
}
