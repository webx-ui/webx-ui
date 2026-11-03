<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Online\Notifications;

/**
 * POST /payments/notify/{method} (spec §9.2): a gateway's server-to-server notification. The route
 * has no session, CSRF, auth or throttle (decision 49): whatever refuses a request before here
 * leaves no event in the journal, so nothing may.
 */
final readonly class NotifyController
{
    public function __invoke(Request $request, Notifications $notifications, string $method): Response
    {
        return $notifications->receive($request, $method);
    }
}
