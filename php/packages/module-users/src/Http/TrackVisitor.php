<?php

declare(strict_types=1);

namespace WebxUi\Users\Http;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Users\Models\User;
use WebxUi\Users\Urls;

/**
 * Two things every page of the site keeps in step with the visitor, in the `web` group.
 *
 * **The other sessions end with a new password** (spec §7 item 4) and with «Sign out everywhere»
 * (§11.1). The session keeps a stamp of the password and the remember token it was opened with;
 * a session whose stamp is not theirs any more is signed out on its next request — the sessions table of a site may be no table at all, so
 * nothing is searched. The remember cookie dies with `remember_token`, which a new password
 * changes.
 *
 * **The cookie `webx_visitor`** (§11.6): `1` — signed in, `u` — signed in, the address not
 * confirmed, none — a guest. No name and no id in it, not encrypted and not httpOnly: `users.js`
 * on a page cached for everybody reads it to show «Account» for «Sign in» and the banner of §8.3.
 *
 * A guest — no session of the guard, no remember cookie, no `webx_visitor` — costs two lookups
 * in arrays and no query.
 */
final readonly class TrackVisitor
{
    public const string COOKIE = 'webx_visitor';

    public const string STAMP = 'webx-users.stamp';

    public function __construct(private Auth $auth) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->auth->guard(Urls::guard());

        if (! $guard instanceof SessionGuard || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $known = $session->has($guard->getName()) || $request->cookies->has($guard->getRecallerName());

        if ($known) {
            $user = $guard->user();
            $stamp = $session->get(self::STAMP);

            if ($user instanceof User && is_string($stamp) && ! hash_equals(self::stamp($user), $stamp)) {
                // Not `logout()`: that would change the remember token of the device that set the
                // new password, too.
                $guard->logoutCurrentDevice();
            }
        }

        $response = $next($request);

        $user = $guard->hasUser() ? $guard->user() : null;

        if ($user instanceof User) {
            $session->put(self::STAMP, self::stamp($user));
        }

        $want = self::state($user instanceof User ? $user : null);
        $have = $request->cookies->get(self::COOKIE);

        if ($want !== $have && ($want !== null || $have !== null)) {
            $response->headers->setCookie(self::cookie($want));
        }

        return $response;
    }

    /**
     * What the session was opened with: the hash of the password and the remember token, hashed
     * again. The token changes only with a new password and «Sign out everywhere» — signing out of
     * one device leaves it (SignIn::out), or that would end the sessions of every other one.
     */
    public static function stamp(User $user): string
    {
        return hash('sha256', 'webx-users|'.$user->getKey().'|'.($user->password ?? '').'|'.($user->getRememberToken() ?? ''));
    }

    /** `1`, `u` or null — what the cookie should say about this visitor. */
    public static function state(?User $user): ?string
    {
        return match (true) {
            $user === null => null,
            $user->hasVerifiedEmail() => '1',
            default => 'u',
        };
    }

    private static function cookie(?string $value): Cookie
    {
        $config = config('session');
        $path = is_array($config) && is_string($config['path'] ?? null) ? $config['path'] : '/';
        $domain = is_array($config) && is_string($config['domain'] ?? null) ? $config['domain'] : null;
        $secure = is_array($config) && is_bool($config['secure'] ?? null) ? $config['secure'] : null;

        // Lives as long as the browser does: a remember cookie that outlives it brings the
        // visitor back on the next request, and this one with them.
        return Cookie::create(self::COOKIE, $value, $value === null ? 1 : 0, $path, $domain, $secure, false, false, Cookie::SAMESITE_LAX);
    }
}
