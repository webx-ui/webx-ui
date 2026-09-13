<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Models\Role;

/**
 * The roles, for the pickers that assign them.
 *
 * Read-only on purpose. A role is a set of permissions, and the set of permissions a panel has
 * is decided by which modules are installed — editing that from a form would be editing the
 * shape of the application from inside it.
 */
final class RoleController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::data(
            Role::query()
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(static fn (Role $role): array => [
                    'id' => $role->id,
                    'slug' => $role->slug,
                    'name' => $role->name,
                    'permissions' => $role->permissions ?? [],
                    'users' => (int) ($role->users_count ?? 0),
                ])
                ->all(),
        );
    }
}
