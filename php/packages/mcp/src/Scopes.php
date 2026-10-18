<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use WebxUi\Admin\Access\AccessPolicy;

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
 *
 * Decided by the panel's one policy ({@see AccessPolicy::tokenAllows()}); this class only knows
 * that `mcp:use` stands for every scope.
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
        return self::policy()->tokenAllows($user, [self::OAUTH, $scope]);
    }

    /**
     * The token the call came in with, whatever the model calls it.
     */
    public static function tokenOf(Authenticatable $user): ?object
    {
        return AccessPolicy::tokenOf($user);
    }

    private static function policy(): AccessPolicy
    {
        return Container::getInstance()->make(AccessPolicy::class);
    }
}
