<?php

declare(strict_types=1);

namespace WebxUi\Debug\Http;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cookie\QueueingFactory as Cookies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\SiteBar\PageResponse;
use WebxUi\Debug\Access;
use WebxUi\Debug\Collecting\Subscriber;
use WebxUi\Debug\Collector;

/**
 * The end of the `web` group, after `StartSession`: the session knows who is signed in by now
 * (§3.2 of the debug bar spec).
 *
 * Keeps the cookie {@see Access::COOKIE} true — set for an administrator with `debug.view` who
 * has none (signed in before the package was installed, cleared their cookies), taken back from
 * one who lost the permission — and, on a page of such an administrator that nobody started
 * collecting early, starts late: the bar then says the early part was not recorded.
 *
 * A visitor costs one look into the session: no login key of the panel's guard, nothing else
 * is asked.
 */
final class CollectLate
{
    public function __construct(
        private readonly Collector $collector,
        private readonly Subscriber $subscriber,
        private readonly Access $access,
        private readonly PageResponse $pages,
        private readonly Cookies $cookies,
        private readonly AuthFactory $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->signedIn($request) && $request->isMethod('GET') && $this->access->enabled()) {
            $this->check($request);
        }

        return $next($request);
    }

    private function check(Request $request): void
    {
        $user = $this->access->administrator();
        $allowed = $this->access->allows($user);
        $marked = $request->cookies->has(Access::COOKIE);

        if ($allowed && ! $marked && $user !== null) {
            $this->cookies->queue($this->access->mark($user));
        } elseif (! $allowed && $marked) {
            $this->cookies->queue($this->access->unmark());
        }

        if ($allowed && ! $this->collector->active && $this->pages->request($request)) {
            $this->subscriber->subscribe();
            $this->collector->start(late: true);
        }
    }

    /** Whether the session holds a login of the panel's guard — no query to learn it. */
    private function signedIn(Request $request): bool
    {
        $guard = $this->access->guard();

        if ($guard === null) {
            return false;
        }

        // Somebody already put on the guard this request (a test, a middleware before this one).
        if ($this->auth->guard($guard)->hasUser()) {
            return true;
        }

        if (! $request->hasSession()) {
            return false;
        }

        foreach (array_keys($request->session()->all()) as $key) {
            if (str_starts_with((string) $key, 'login_'.$guard.'_')) {
                return true;
            }
        }

        return false;
    }
}
