<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Auth\Models\CmsUser;

/**
 * The panel's API answers 401 rather than redirecting: there is no server-rendered login page
 * to redirect to, and the front end is waiting for exactly this answer to draw its own.
 */
final class Authenticate
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->auth->guard((string) config('webx-auth.guard'));
        $user = $guard->user();

        if (! $user instanceof CmsUser) {
            return response()->json(['message' => __('webx-auth::errors.unauthenticated')], 401);
        }

        // An account switched off mid-session stops working now, not at its next sign-in.
        if (! $user->is_active) {
            return response()->json(['message' => __('webx-auth::errors.inactive')], 403);
        }

        return $next($request);
    }
}
