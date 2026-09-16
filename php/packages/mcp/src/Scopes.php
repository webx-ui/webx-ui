<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Whether the caller may use a tool of a given scope.
 *
 * A scope is an ability of the token the call came in with — `blocks:write` on a Sanctum
 * token is what lets an agent write blocks. A caller without a token is not refused here:
 * either there is no caller at all (the local stdio server, an artisan process that can do
 * anything already), or a person is signed in through a session, and the middleware that let
 * them in is the authority on what they may do.
 */
final class Scopes
{
    public static function allows(?Authenticatable $user, string $scope): bool
    {
        if ($user === null) {
            return true;
        }

        if (! method_exists($user, 'currentAccessToken') || ! method_exists($user, 'tokenCan')) {
            return true;
        }

        if ($user->currentAccessToken() === null) {
            return true;
        }

        return (bool) $user->tokenCan($scope);
    }
}
