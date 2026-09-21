<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Mcp\Registry\BoundTool;

/**
 * Whether the administrator a call acts as may use a tool.
 *
 * The scope says what the token may do and the grant says what the person agreed to; this is
 * the third question, and for a token granted through consent the one that actually limits
 * anything: such a token carries one scope for the whole server, so an agent let in by an
 * editor can do exactly what that editor can do in the panel, and no more. The permissions a
 * tool is behind are the ones its module's routes are behind for the same work.
 *
 * A caller with no permissions to ask about is not refused: there is nobody there (the local
 * stdio server), or whoever let them in did not hand the server an administrator, and that
 * middleware is the authority on what they may do.
 */
final class Permissions
{
    public static function allows(?Authenticatable $user, BoundTool $tool): bool
    {
        if (! $user instanceof HasPermissions) {
            return true;
        }

        foreach ($tool->permissions() as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
