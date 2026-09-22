<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Auth\Models\CmsUser;

/**
 * The consent screen is a page behind the panel's sign-in, and the panel's sign-in is a
 * screen of the panel — there is no server-rendered login page for Passport to send a guest
 * to, and left to itself it sends them to a route named `login` that no site here has.
 *
 * So a guest is sent to the panel, told where to come back to, and the panel brings them
 * back once they are in. A person switched off since they signed in is shown the door here
 * too, the way the panel's API shows it.
 */
final class SignInBeforeConsent
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        if (! $user instanceof CmsUser) {
            return new RedirectResponse(self::signInUrl($request->fullUrl()));
        }

        if (! $user->is_active) {
            abort(403, (string) __('webx-auth::errors.inactive'));
        }

        return $next($request);
    }

    /**
     * The panel's sign-in screen, and after it the page asked for — as long as that page is
     * on this site: `next` is whatever the address bar said, and an address on another host
     * would make the sign-in form a way to send people elsewhere.
     */
    public static function signInUrl(?string $next): string
    {
        $panel = trim((string) config('webx-admin.path', 'cms'), '/');
        $login = trim((string) config('webx-auth.login_path', 'login'), '/');
        $url = url($panel === '' ? $login : $panel.'/'.$login);

        if ($next === null || ! str_starts_with($next, rtrim(url('/'), '/').'/')) {
            return $url;
        }

        return $url.'?next='.rawurlencode($next);
    }
}
