<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Resources;

use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;

/**
 * What the panel is told about whoever is signed in.
 *
 * Permissions are flattened deliberately: the front end hides what a person may not do, and
 * it should not have to walk roles to work that out.
 */
final class CmsUserPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function make(CmsUser $user): array
    {
        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'isSuper' => $user->is_super,
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            'roles' => $user->roles
                ->map(static fn (Role $role): array => [
                    'slug' => $role->slug,
                    'name' => $role->name,
                ])
                ->values()
                ->all(),
            'permissions' => $user->permissions(),
        ];
    }
}
