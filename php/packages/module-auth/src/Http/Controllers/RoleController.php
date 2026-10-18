<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Access\AccessRules;
use WebxUi\Auth\Access\PermissionMatrix;
use WebxUi\Auth\Http\Requests\RoleRequest;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;

/**
 * The roles: the list the pickers assign from, and the screen that makes them
 * (WEBX_UI_PERMISSIONS.md §11).
 *
 * Reading is open to whoever deals with people or roles at all; writing is `roles.manage` and
 * then {@see AccessRules} — a role gains only what its editor holds (rule 2), and a role the
 * editor holds is not theirs to edit or delete (rule 5). Which permissions exist is still the
 * modules' to say: a role lists them, it does not invent them ({@see RoleRequest}).
 */
final class RoleController
{
    public function __construct(private readonly AccessRules $rules) {}

    public function index(Request $request): JsonResponse
    {
        $held = $this->heldBy($request);

        return ApiResponse::data(
            Role::query()
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => $this->describe($role, $held))
                ->all(),
        );
    }

    /** One role, with the people who hold it — what deleting it would take away from. */
    public function show(Request $request, Role $role): JsonResponse
    {
        $role->loadCount('users');

        return ApiResponse::data($this->describe($role, $this->heldBy($request)) + [
            'holders' => $role->users()
                ->orderBy('name')
                ->get(['cms_users.id', 'cms_users.name', 'cms_users.email'])
                ->map(static fn (CmsUser $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
                ->all(),
        ]);
    }

    /** Every permission a role may list, by module, and which of them the reader holds. */
    public function permissions(Request $request, PermissionMatrix $matrix): JsonResponse
    {
        return ApiResponse::data($matrix->for($request->user()));
    }

    public function store(RoleRequest $request): JsonResponse
    {
        /** @var list<string> $permissions */
        $permissions = array_values(array_map('strval', (array) $request->input('permissions', [])));

        $refusal = $this->rules->saveRole($this->actor(), null, $permissions);

        if ($refusal !== null) {
            return $refusal->toResponse();
        }

        $role = Role::query()->create([
            'name' => (string) $request->string('name'),
            'slug' => $this->slug($request, (string) $request->string('name')),
            'permissions' => $permissions,
        ]);

        return ApiResponse::data($this->describe($role->loadCount('users'), []), 201);
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $permissions = $request->has('permissions')
            ? array_values(array_map('strval', (array) $request->input('permissions', [])))
            : ($role->permissions ?? []);

        $refusal = $this->rules->saveRole($this->actor(), $role, $permissions);

        if ($refusal !== null) {
            return $refusal->toResponse();
        }

        if ($request->has('name')) {
            $role->name = (string) $request->string('name');
        }

        if ($request->filled('slug')) {
            $role->slug = (string) $request->string('slug');
        }

        $role->permissions = $permissions;
        $role->save();

        return ApiResponse::data($this->describe($role->loadCount('users'), $this->heldBy($request)));
    }

    public function destroy(Role $role): JsonResponse
    {
        $refusal = $this->rules->deleteRole($this->actor(), $role);

        if ($refusal !== null) {
            return $refusal->toResponse();
        }

        // Everybody who held it loses it, and each account's journal says so — the deletion of
        // the role alone would not tell anybody reading one person's history.
        foreach ($role->users()->with('roles')->get() as $holder) {
            $before = $holder->roles->pluck('slug')->map(static fn (mixed $slug): string => (string) $slug)->all();
            $after = array_values(array_filter($before, static fn (string $slug): bool => $slug !== $role->slug));
            $this->rules->recordRoles($holder, $before, $after);
        }

        $role->users()->detach();
        $role->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    /**
     * @param  list<int>  $held  the ids of the roles the reader holds
     * @return array<string, mixed>
     */
    private function describe(Role $role, array $held): array
    {
        return [
            'id' => $role->id,
            'slug' => $role->slug,
            'name' => $role->name,
            'permissions' => $role->permissions ?? [],
            'users' => (int) ($role->users_count ?? 0),
            // Rule 5, said ahead: the form opens such a role read-only instead of failing on save.
            'held' => in_array((int) $role->id, $held, true),
        ];
    }

    /**
     * @return list<int>
     */
    private function heldBy(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof CmsUser) {
            return [];
        }

        return $user->roles()->pluck('cms_roles.id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /** The code asked for, or one made from the name that no other role has yet. */
    private function slug(RoleRequest $request, string $name): string
    {
        if ($request->filled('slug')) {
            return (string) $request->string('slug');
        }

        return Role::freeSlug($name);
    }

    private function actor(): ?CmsUser
    {
        $current = Auth::guard((string) config('webx-auth.guard'))->user();

        return $current instanceof CmsUser ? $current : null;
    }
}
