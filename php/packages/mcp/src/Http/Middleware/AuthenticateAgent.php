<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door of the HTTP server.
 *
 * Asks the configured guard — Sanctum's, reading a bearer token — for a user and answers 401
 * without one, as JSON: an MCP client is a program, and a redirect to a login page would be
 * a parse error to it. An administrator switched off since the token was issued is refused
 * too, the way the panel refuses a session of theirs. From here on the guard is the default,
 * so that `$request->user()` in a tool is this administrator.
 */
final class AuthenticateAgent
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = (string) $this->config->get('webx-mcp.guard', 'sanctum');
        $user = $this->auth->guard($guard)->user();

        if ($user === null) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        if ($user instanceof Model && $user->getAttribute('is_active') === false) {
            return new JsonResponse(['message' => 'This account is switched off.'], 403);
        }

        $this->auth->shouldUse($guard);

        return $next($request);
    }
}
