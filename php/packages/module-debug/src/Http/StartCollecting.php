<?php

declare(strict_types=1);

namespace WebxUi\Debug\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Debug\Access;
use WebxUi\Debug\Collecting\Subscriber;
use WebxUi\Debug\Collector;
use WebxUi\Debug\Mode;

/**
 * The first global middleware (§3.2 of the debug bar spec): start collecting before anything
 * else of the page runs, when the request says it may be an administrator's.
 *
 * Nothing is known yet about who is asking — the session is not started — so the request only
 * has to *say* so: the cookie {@see Access::COOKIE} a browser of an administrator carries, or the
 * token header of `debug_page`. The cookie is not read here; whether it is true is decided when
 * the response is ({@see Insertion}), and a request that lied loses what was collected. A token
 * is opened — signed and not expired, its single use left to the end — so a forged header starts
 * nothing. A visitor costs a look at two arrays.
 *
 * Where only the agent's path is open (production, §3.3), the cookie starts nothing either.
 */
final class StartCollecting
{
    public function __construct(
        private readonly Collector $collector,
        private readonly Subscriber $subscriber,
        private readonly Access $access,
        private readonly Mode $mode,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->collector->active && $request->isMethod('GET') && $this->access->enabled()) {
            $token = $request->headers->get(Access::HEADER);

            if (is_string($token) && $token !== '') {
                if (! $this->access->plausible($token)) {
                    return $next($request);
                }

                $this->subscriber->subscribe();
                $this->collector->start(token: $token);
            } elseif (! $this->mode->agentOnly && $request->cookies->has(Access::COOKIE)) {
                $this->subscriber->subscribe();
                $this->collector->start();
            }
        }

        return $next($request);
    }
}
