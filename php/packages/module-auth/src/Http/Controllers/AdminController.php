<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Access\AccessRules;
use WebxUi\Auth\Http\Requests\AdminIndexRequest;
use WebxUi\Auth\Http\Requests\AdminRequest;
use WebxUi\Auth\Http\Resources\AdminResource;
use WebxUi\Auth\Models\CmsUser;

/**
 * The administrators of this panel.
 *
 * What may be done to an account is not decided here but by {@see AccessRules}, the same rules
 * the MCP tools and the keys answer to (WEBX_UI_PERMISSIONS.md §6): nobody switches themselves
 * off or changes their own roles, nobody hands out more than they hold, only a super
 * administrator makes or touches one, and the last active one stays.
 *
 * An edit changes what it sends and nothing else: a form that leaves out «Super administrator»
 * does not take the status away, and one that leaves out «Active» does not switch anybody on.
 */
final class AdminController
{
    /** The columns an edit may set besides the password and the roles. */
    private const FIELDS = ['name', 'email', 'avatar', 'locale', 'is_active', 'is_super'];

    private const FLAGS = ['is_active', 'is_super'];

    public function __construct(private readonly AccessRules $rules) {}

    public function index(AdminIndexRequest $request): AnonymousResourceCollection
    {
        $query = CmsUser::query()->with('roles');

        if ($request->filled('q')) {
            $term = '%'.str_replace('%', '\%', (string) $request->string('q')).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        if ($request->filled('role')) {
            $slug = (string) $request->string('role');
            $query->whereHas('roles', static fn ($roles) => $roles->where('slug', $slug));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->string('active')->value() === 'yes');
        }

        $sort = (string) ($request->string('sort')->value() ?: 'name');
        $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');

        return AdminResource::collection($query->paginate($request->integer('per_page') ?: 20));
    }

    public function show(CmsUser $admin): JsonResponse
    {
        return ApiResponse::data(new AdminResource($admin));
    }

    public function store(AdminRequest $request): JsonResponse
    {
        /** @var list<int> $roles */
        $roles = array_values(array_map('intval', (array) $request->input('roles', [])));

        $refusal = $this->rules->create($this->actor(), $request->boolean('is_super'), $roles);

        if ($refusal !== null) {
            return $refusal->toResponse();
        }

        $admin = new CmsUser([
            'name' => (string) $request->string('name'),
            'email' => (string) $request->string('email'),
            'avatar' => $request->input('avatar'),
            'is_active' => $request->boolean('is_active', true),
            'is_super' => $request->boolean('is_super'),
            'locale' => $request->input('locale'),
        ]);
        $admin->password = (string) $request->string('password');
        $admin->save();

        $admin->roles()->sync($roles);
        $this->rules->recordRoles($admin, [], $this->slugs($admin));

        return ApiResponse::data(new AdminResource($admin->refresh()), 201);
    }

    public function update(AdminRequest $request, CmsUser $admin): JsonResponse
    {
        $changes = $this->changes($request, $admin);

        $refusal = $this->rules->edit($this->actor(), $admin, $changes);

        if ($refusal !== null) {
            return $refusal->toResponse();
        }

        $admin->fill(array_intersect_key($changes, array_flip(self::FIELDS)));

        // Only when something was typed: an edit that leaves the field blank is an edit of
        // everything else, not a request to change the password to nothing.
        if (array_key_exists('password', $changes)) {
            $admin->password = (string) $request->string('password');
        }

        $admin->save();

        if (array_key_exists('password', $changes)) {
            $this->rules->recordPassword($admin);
        }

        if (array_key_exists('roles', $changes)) {
            $before = $this->slugs($admin);
            $admin->roles()->sync($changes['roles']);
            $this->rules->recordRoles($admin, $before, $this->slugs($admin->load('roles')));
        }

        return ApiResponse::data(new AdminResource($admin->refresh()));
    }

    public function destroy(CmsUser $admin): JsonResponse
    {
        $refusal = $this->rules->delete($this->actor(), $admin);

        if ($refusal !== null) {
            return $refusal->toResponse();
        }

        $admin->roles()->detach();
        $admin->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    /**
     * What the request would really change: the fields it sent whose value differs, the
     * password when one was typed, the roles when they were sent at all.
     *
     * @return array<string, mixed>
     */
    private function changes(AdminRequest $request, CmsUser $admin): array
    {
        $changes = [];

        foreach (self::FIELDS as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = in_array($field, self::FLAGS, true) ? $request->boolean($field) : $request->input($field);
            $current = in_array($field, self::FLAGS, true) ? (bool) $admin->getAttribute($field) : $admin->getAttribute($field);

            if ($value !== $current) {
                $changes[$field] = $value;
            }
        }

        if ($request->filled('password')) {
            $changes['password'] = true;
        }

        if ($request->has('roles')) {
            $changes['roles'] = array_values(array_map('intval', (array) $request->input('roles', [])));
        }

        return $changes;
    }

    /**
     * @return list<string>
     */
    private function slugs(CmsUser $admin): array
    {
        return $admin->roles()->pluck('slug')->map(static fn (mixed $slug): string => (string) $slug)->all();
    }

    private function actor(): ?CmsUser
    {
        $current = Auth::guard((string) config('webx-auth.guard'))->user();

        return $current instanceof CmsUser ? $current : null;
    }
}
