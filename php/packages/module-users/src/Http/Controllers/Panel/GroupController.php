<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Panel\Groups;

/**
 * `/api/cms/users/groups` (spec §16.2): the customer groups through {@see Groups} — the class the
 * agent's `users_groups_*` write through too.
 */
final readonly class GroupController
{
    public function __construct(private Groups $groups) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data($this->groups->rows());
    }

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::data(Groups::row($this->groups->save($this->input($request))), 201);
    }

    public function update(Request $request, int $group): JsonResponse
    {
        return ApiResponse::data(Groups::row($this->groups->save($this->input($request), Group::query()->findOrFail($group))));
    }

    public function destroy(int $group): JsonResponse
    {
        $this->groups->delete(Group::query()->findOrFail($group));

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        return ['code' => $request->input('code'), 'name' => $request->input('name')];
    }
}
