<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Auth\Models\CmsUser;

/**
 * `->middleware('cms.can:pages.manage')` on a module's own routes.
 *
 * Several permissions mean any of them: a screen usually needs one of a few ways in.
 */
final class EnsurePermission
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        if (! $user instanceof CmsUser) {
            return response()->json(['message' => __('webx-auth::errors.unauthenticated')], 401);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => __('webx-auth::errors.forbidden'),
            'required' => array_values($permissions),
        ], 403);
    }
}
