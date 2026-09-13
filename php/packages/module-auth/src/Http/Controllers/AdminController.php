<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Http\Requests\AdminIndexRequest;
use WebxUi\Auth\Http\Requests\AdminRequest;
use WebxUi\Auth\Http\Resources\AdminResource;
use WebxUi\Auth\Models\CmsUser;

/**
 * The administrators of this panel.
 *
 * Two rules are enforced here rather than left to whoever is clicking: nobody can switch
 * themselves off or delete themselves, and the last super administrator cannot be taken away.
 * Both are ways to lock everybody out of a panel with no way back in.
 */
final class AdminController
{
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
        $admin = new CmsUser($this->attributes($request));
        $admin->password = (string) $request->string('password');
        $admin->save();

        $admin->roles()->sync($request->input('roles', []));

        return ApiResponse::data(new AdminResource($admin->refresh()), 201);
    }

    public function update(AdminRequest $request, CmsUser $admin): JsonResponse
    {
        $refusal = $this->wouldLockEverybodyOut($request, $admin);

        if ($refusal !== null) {
            return ApiResponse::message($refusal, 422);
        }

        $admin->fill($this->attributes($request));

        // Only when something was typed: an edit that leaves the field blank is an edit of
        // everything else, not a request to change the password to nothing.
        if ($request->filled('password')) {
            $admin->password = (string) $request->string('password');
        }

        $admin->save();

        if ($request->has('roles')) {
            $admin->roles()->sync($request->input('roles', []));
        }

        return ApiResponse::data(new AdminResource($admin->refresh()));
    }

    public function destroy(CmsUser $admin): JsonResponse
    {
        if ($this->isSelf($admin)) {
            return ApiResponse::message(__('webx-auth::errors.not-yourself'), 422);
        }

        if ($admin->is_super && $this->lastSuper($admin)) {
            return ApiResponse::message(__('webx-auth::errors.last-super'), 422);
        }

        $admin->roles()->detach();
        $admin->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(AdminRequest $request): array
    {
        return [
            'name' => (string) $request->string('name'),
            'email' => (string) $request->string('email'),
            'avatar' => $request->input('avatar'),
            'is_active' => $request->boolean('is_active', true),
            'is_super' => $request->boolean('is_super'),
            'locale' => $request->input('locale'),
        ];
    }

    /** The two edits that leave nobody able to sign in and put things back. */
    private function wouldLockEverybodyOut(AdminRequest $request, CmsUser $admin): ?string
    {
        if ($this->isSelf($admin) && ! $request->boolean('is_active', true)) {
            return (string) __('webx-auth::errors.not-yourself');
        }

        $losingSuper = $admin->is_super && ! $request->boolean('is_super');

        if ($losingSuper && $this->lastSuper($admin)) {
            return (string) __('webx-auth::errors.last-super');
        }

        return null;
    }

    private function isSelf(CmsUser $admin): bool
    {
        $current = Auth::guard((string) config('webx-auth.guard'))->user();

        return $current instanceof CmsUser && $current->getKey() === $admin->getKey();
    }

    private function lastSuper(CmsUser $admin): bool
    {
        return ! CmsUser::query()
            ->where('is_super', true)
            ->whereKeyNot($admin->getKey())
            ->exists();
    }
}
