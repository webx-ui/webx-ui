<?php

declare(strict_types=1);

namespace WebxUi\Users\Panel;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\Users\Models\Group;

/**
 * The customer groups (spec §16.2, decision 24) as the panel and the agent both write them: a
 * code, a name in one language, how many visitors each holds. By name, no drag. Deleting one takes
 * its links to the visitors with it; nobody refuses (§13.1).
 */
final readonly class Groups
{
    /**
     * Every group with its count of visitors, by name.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return Group::query()
            ->withCount('users')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(static fn (Group $group): array => self::row($group))
            ->values()
            ->all();
    }

    /**
     * A new group, or the one given changed — checked as the dialog checks it.
     *
     * @param  array<string, mixed>  $input  `code`, `name`
     *
     * @throws ValidationException
     */
    public function save(array $input, ?Group $group = null): Group
    {
        $group ??= new Group;
        $group->fill($this->validated($input, $group->exists ? $group : null));
        $group->save();

        return $group->loadCount('users');
    }

    public function delete(Group $group): void
    {
        $group->getConnection()->transaction(static function () use ($group): void {
            // The pivot cascades on a database that keeps the foreign keys; this does it everywhere.
            $group->users()->detach();
            $group->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Group $group): array
    {
        return [
            'id' => (int) $group->getKey(),
            'code' => $group->code,
            'name' => $group->name,
            'users_count' => (int) ($group->getAttribute('users_count') ?? 0),
            'created_at' => $group->created_at?->toAtomString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{code: string, name: string}
     *
     * @throws ValidationException
     */
    private function validated(array $input, ?Group $group): array
    {
        $code = $input['code'] ?? null;
        $name = $input['name'] ?? null;

        $input = [
            'code' => is_string($code) ? strtolower(trim($code)) : $code,
            'name' => is_string($name) ? trim($name) : $name,
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
}
