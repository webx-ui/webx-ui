<?php

declare(strict_types=1);

namespace WebxUi\Debug\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Debug\Access;
use WebxUi\Debug\Collecting\Subscriber;
use WebxUi\Debug\Collector;

/**
 * The first global middleware (§3.2 of the debug bar spec): start collecting before anything
 * else of the page runs, when the request says it may be an administrator's.
 *
 * Nothing is known yet about who is asking — the session is not started — so the request only
 * has to *say* so: the cookie {@see Access::COOKIE} a browser of an administrator carries, or the
 * token header of `debug_page`. Neither is read here; whether it is true is decided when the
 * response is ({@see Insertion}), and a request that lied loses what was collected. A visitor
 * costs a look at two arrays.
 */
final class StartCollecting
{
    public function __construct(
        private readonly Collector $collector,
        private readonly Subscriber $subscriber,
        private readonly Access $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->collector->active && $request->isMethod('GET') && $this->access->enabled()) {
            $token = $request->headers->get(Access::HEADER);

            if (is_string($token) && $token !== '') {
                $this->subscriber->subscribe();
                $this->collector->start(token: $token);
            } elseif ($request->cookies->has(Access::COOKIE)) {
                $this->subscriber->subscribe();
                $this->collector->start();
            }
        }

        return $next($request);
    }
}
