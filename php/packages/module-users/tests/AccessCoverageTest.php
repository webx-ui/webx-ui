<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Users\Http\KeepPrivate;

/**
 * Every route of the module is the visitor's own door, open by design (spec §15): named here with
 * the reason and the limit that stands in front of it. None is behind the panel's guard, every one
 * is in the web group (the session, CSRF) and kept out of every cache. A new route fails here
 * until somebody says why it may be open.
 */
final class AccessCoverageTest extends TestCase
{
    private const array OPEN = [
        'GET account/sign-in' => 'the visitor\'s sign-in: a form, nothing behind it',
        'POST account/sign-in' => 'the visitor\'s sign-in: 5 a minute per address and IP, 30 per IP (Throttle::signIn)',
        'GET account/register' => 'the visitor\'s registration: a form, nothing behind it',
        'POST account/register' => 'the visitor\'s registration: 5 an hour per IP (Throttle::register)',
        'POST account/sign-out' => 'the visitor\'s sign-out: only their own session, behind CSRF',
        'GET account/forgot' => 'the request of a reset link: a form, nothing behind it',
        'POST account/forgot' => 'the request of a reset link: one answer for every address; letters 3 an hour per address and IP, 20 per IP (Throttle::forgot)',
        'GET account/reset/{token}' => 'the page of a reset link: the token is the key, 32 random bytes',
        'POST account/reset/{token}' => 'the new password of a reset link: 10 a minute per IP (Throttle::reset)',
        'GET account/verify/{id}/{hash}' => 'the link that confirms an address: signed, alive 72 hours',
        'POST account/verify/resend' => 'the letter once more: the signed-in visitor\'s own (webx.visitor), once a minute and 5 an hour (Throttle::verify)',
        'POST account/email' => 'the letter to a new address: the signed-in visitor\'s own (webx.visitor) with the current password, 3 an hour (Throttle::emailChange)',
        'GET account/email/{id}' => 'the link that moves the sign-in to a new address: signed, alive 60 minutes',
        'GET account/state' => 'what a cached page may say of the visitor looking at it: their own, the address masked',
        'GET account' => 'the account: the signed-in visitor\'s own (webx.visitor), a redirect to the first section',
        'GET account/profile' => 'the profile: the signed-in visitor\'s own (webx.visitor)',
        'POST account/profile' => 'the profile saved: the signed-in visitor\'s own (webx.visitor), behind CSRF',
        'GET account/security' => 'sign-in and security: the signed-in visitor\'s own (webx.visitor)',
        'POST account/password' => 'a new password: the signed-in visitor\'s own (webx.visitor) with the current one, 5 wrong a minute (Throttle::password)',
        'POST account/password/link' => '«Set a password»: a reset link to the signed-in visitor\'s own address (webx.visitor), 3 an hour (Throttle::passwordLink)',
        'POST account/sign-out-everywhere' => 'the other sessions ended: the signed-in visitor\'s own (webx.visitor), behind CSRF',
        'POST account/delete' => 'the account deleted: the signed-in visitor\'s own (webx.visitor) with the current password or the address, 5 wrong a minute (Throttle::password)',
        'GET account/auth/{provider}' => 'off to a social network that is shown: 20 a minute per IP (Throttle::social); `?link=1` only for the signed-in visitor',
        'GET account/auth/{provider}/callback' => 'the return of a social network, GET or POST: the session\'s one-time `state`, outside CSRF (spec §9.4)',
        'GET account/auth/complete' => '«Almost done»: only with the network\'s profile waiting in this session',
        'POST account/auth/complete' => '«Almost done» sent: the profile waiting in this session, 5 an hour per IP (Throttle::register)',
        'POST account/social/{provider}/unlink' => 'a network unlinked: the signed-in visitor\'s own (webx.visitor), never the last way to sign in, behind CSRF',
        'GET webx/users/users.js' => 'the script of the cached pages: the same file for everyone',
    ];

    /** The same file for everyone: no session, no language, cached for good. */
    private const array STATIC = ['GET webx/users/users.js'];

    #[Test]
    public function every_route_is_a_door_of_the_visitor_and_says_so(): void
    {
        $found = [];
        $problems = [];

        foreach ($this->app->make('router')->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with(ltrim($route->getActionName(), '\\'), 'WebxUi\\Users\\')) {
                continue;
            }

            // The copy behind the language prefix is the same door.
            $key = array_values(array_diff($route->methods(), ['HEAD']))[0].' '.preg_replace('#^\{webxLocale\}/#', '', $route->uri());
            $found[] = $key;
            $middleware = array_filter($route->gatherMiddleware(), is_string(...));

            if (! array_key_exists($key, self::OPEN)) {
                $problems[] = "{$key}: not named as open.";
            }

            if (in_array($key, self::STATIC, true)) {
                continue;
            }

            if (! in_array('web', $middleware, true)) {
                $problems[] = "{$key}: outside the web group — no session, no CSRF.";
            }

            if (in_array('cms.auth', $middleware, true) || in_array('webx.panel', $middleware, true)) {
                $problems[] = "{$key}: behind the panel's guard — a visitor is never an administrator.";
            }

            if (! in_array(KeepPrivate::class, $middleware, true)) {
                $problems[] = "{$key}: may be cached.";
            }

            if (($route->action['webx_service'] ?? false) !== true) {
                $problems[] = "{$key}: not a service route — a link field would offer it as a page of the site.";
            }
        }

        foreach (array_keys(self::OPEN) as $key) {
            if (! in_array($key, $found, true)) {
                $problems[] = "{$key}: named as open, and there is no such route.";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }
}
