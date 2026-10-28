<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Users\Models\Group;

/**
 * `/api/cms/users/groups` (spec §16.2, decision 24): the customer groups — a code, a name in one
 * language, how many visitors each holds. By name, no drag. Deleting one takes its links to the
 * visitors with it; nobody refuses (§13.1).
 */
final readonly class GroupController
{
    public function index(): JsonResponse
    {
        return ApiResponse::data(Group::query()
            ->withCount('users')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(static fn (Group $group): array => self::row($group))
            ->all());
    }

    public function store(Request $request): JsonResponse
    {
        $group = new Group($this->validated($request, null));
        $group->save();

        return ApiResponse::data(self::row($group->loadCount('users')), 201);
    }

    public function update(Request $request, int $group): JsonResponse
    {
        $found = Group::query()->findOrFail($group);
        $found->fill($this->validated($request, $found));
        $found->save();

        return ApiResponse::data(self::row($found->loadCount('users')));
    }

    public function destroy(int $group): JsonResponse
    {
        $found = Group::query()->findOrFail($group);
        // The pivot cascades on a database that keeps the foreign keys; this does it everywhere.
        $found->users()->detach();
        $found->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array{code: string, name: string}
     */
    private function validated(Request $request, ?Group $group): array
    {
        $input = [
            'code' => is_string($request->input('code')) ? strtolower(trim($request->input('code'))) : $request->input('code'),
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
        ];

        /** @var array{code: string, name: string} $valid */
        $valid = validator($input, [
            'code' => ['required', 'string', 'max:64', 'regex:'.Group::CODE, Rule::unique('users_groups', 'code')->ignore($group?->getKey())],
            'name' => ['required', 'string', 'max:255'],
        ], [
            'code.regex' => (string) trans('webx-users::errors.group-code'),
            'code.unique' => (string) trans('webx-users::errors.group-taken'),
        ], [
            'code' => (string) trans('webx-users::screen.group-code'),
            'name' => (string) trans('webx-users::screen.group-name'),
        ])->validate();

        return $valid;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(Group $group): array
    {
        return [
            'id' => (int) $group->getKey(),
            'code' => $group->code,
            'name' => $group->name,
            'users_count' => (int) ($group->getAttribute('users_count') ?? 0),
            'created_at' => $group->created_at?->toAtomString(),
        ];
    }
}
