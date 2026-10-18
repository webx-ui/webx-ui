<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Auth\Models\CmsUser;

/**
 * `->middleware('cms.can:pages.manage')` on a module's own routes.
 *
 * Several permissions mean any of them: a screen usually needs one of a few ways in. The answer
 * is the policy's, the same one every other door asks.
 *
 * It runs before the route's bindings (AuthServiceProvider puts it ahead of them): somebody
 * without the permission is told no, not whether the record they asked about exists.
 */
final class EnsurePermission
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly AccessPolicy $policy,
    ) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        if (! $user instanceof CmsUser) {
            return response()->json(['message' => __('webx-auth::errors.unauthenticated')], 401);
        }

        $decision = $this->policy->decide($user, array_values($permissions));

        if ($decision->allowed) {
            return $next($request);
        }

        return response()->json([
            'message' => __('webx-auth::errors.forbidden'),
            'required' => $decision->required,
        ], 403);
    }
}
