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
    ];

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
