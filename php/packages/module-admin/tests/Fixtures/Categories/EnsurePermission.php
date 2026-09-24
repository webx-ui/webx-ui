<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Categories;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Contracts\HasPermissions;

/**
 * `cms.can` as `module-auth` answers it — any of the named permissions lets the request through —
 * for a test of the frame, which does not require the package that owns the real one.
 */
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if ($user instanceof HasPermissions && $user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
