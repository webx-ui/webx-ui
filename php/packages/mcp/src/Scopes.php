<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Whether the caller may use a tool of a given scope.
 *
 * Two kinds of token reach this door, and they say different things.
 *
 * A token issued through the OAuth flow carries one scope for the whole server — `mcp:use`,
 * the one `laravel/mcp` advertises — because that is the only scope a client is ever offered
 * there. It says "this agent may talk to the panel" and nothing about which module; what
 * limits such a call is the administrator's own permissions, checked elsewhere. So a token
 * that carries it passes here, and reading module scopes off it would refuse everything.
 *
 * A token that names module scopes — a key issued for a machine — is read scope by scope.
 *
 * A caller without a token at all is not refused: either there is no caller (the local stdio
 * server, an artisan process that can do anything already), or a person is signed in through
 * a session, and the middleware that let them in is the authority on what they may do.
 */
final class Scopes
{
    /**
     * `Laravel\Mcp\Server\Registrar::OAUTH_SCOPE`, spelled out rather than imported: this
     * class is asked on every call, and the constant is the contract between us.
     */
    public const OAUTH = 'mcp:use';

    public static function allows(?Authenticatable $user, string $scope): bool
    {
        if ($user === null || ! method_exists($user, 'tokenCan')) {
            return true;
        }

        if (self::tokenOf($user) === null) {
            return true;
        }

        return $user->tokenCan(self::OAUTH) || $user->tokenCan($scope);
    }

    /**
     * The token the call came in with, whatever the model calls it.
     *
     * Passport names it both ways and answers null for a user who arrived by session;
     * asking `tokenCan()` alone would not tell the two apart, and a session user would be
     * refused everything rather than left to the middleware.
     */
    public static function tokenOf(Authenticatable $user): ?object
    {
        foreach (['currentAccessToken', 'token'] as $method) {
            if (! method_exists($user, $method)) {
                continue;
            }

            $token = $user->{$method}();

            if (is_object($token)) {
                return $token;
            }
        }

        return null;
    }
}
