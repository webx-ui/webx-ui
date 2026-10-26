<?php

declare(strict_types=1);

namespace WebxUi\Admin\Gate;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Throwable;
use WebxUi\Admin\Contracts\HasPermissions;

/**
 * Whether the request comes from somebody signed in to the panel — asked by the password over
 * the site, which runs before any session has started.
 *
 * The gate keeps strangers off a site in testing; an administrator signed in to the panel is not
 * one, and already sees more than the site shows (its drafts). Without this, every place where
 * the panel shows or opens the site — the preview of a page and whatever it loads (the forms'
 * script, a form's token), a preview reloaded after its token ran out, a link followed inside
 * it, «Open on the site» — asks that administrator for a second password the panel never told
 * them about.
 *
 * Read-only on purpose: the session is read through a copy of the store and the account through
 * the guard's provider, so the request's own session and the guard are left exactly as the `web`
 * group will find them. Nothing is written, no cookie is set, and a request without the
 * session cookie costs nothing.
 */
final class PanelSession
{
    public function __construct(
        private readonly Config $config,
        private readonly AuthFactory $auth,
        private readonly SessionManager $sessions,
        private readonly Encrypter $encrypter,
    ) {}

    public function signedIn(Request $request): bool
    {
        $name = $this->config->get('webx-auth.guard');

        // A site without `module-auth` has no guard to name, and so nobody signed in to a panel.
        if (! is_string($name) || $name === '' || $this->config->get("auth.guards.{$name}") === null) {
            return false;
        }

        try {
            $guard = $this->auth->guard($name);

            if (! $guard instanceof SessionGuard) {
                return false;
            }

            $session = clone $this->sessions->driver();
            $id = $this->sessionId($request, $session->getName());

            if ($id === null) {
                return false;
            }

            $session->setId($id);
            $session->start();

            $key = $session->get($guard->getName());

            if (! is_int($key) && ! is_string($key)) {
                return false;
            }

            $user = $guard->getProvider()->retrieveById($key);
        } catch (Throwable) {
            // A store that is down or a cookie from another key is a stranger, not a 500.
            return false;
        }

        if (! $user instanceof Model || ! $user instanceof HasPermissions) {
            return false;
        }

        // A switched-off account keeps its session until it next asks the panel for something;
        // it does not keep a way past the password meanwhile.
        return $user->getAttribute('is_active') !== false && $user->getAttribute('is_active') !== 0;
    }

    /** The session id from its cookie, still encrypted out here: `EncryptCookies` is in `web`. */
    private function sessionId(Request $request, string $cookie): ?string
    {
        $value = $request->cookies->get($cookie);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $decrypted = $this->encrypter->decrypt($value, false);
        } catch (DecryptException) {
            return null;
        }

        $id = is_string($decrypted) ? CookieValuePrefix::validate($cookie, $decrypted, $this->encrypter->getAllKeys()) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
