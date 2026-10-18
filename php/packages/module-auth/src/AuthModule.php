<?php

declare(strict_types=1);

namespace WebxUi\Auth;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Admin\Panel\SystemSections;
use WebxUi\Auth\Access\AccessRules;
use WebxUi\Auth\Access\PermissionMatrix;
use WebxUi\Auth\Http\Requests\RoleRequest;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\LoginRecord;
use WebxUi\Auth\Models\Role;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The panel section for administrators and roles.
 *
 * Its MCP tools stop where they should: an agent can see who has what, make and edit roles and
 * move people between them — under the same rules as the panel, each change in the journal and
 * undone by the opposite call — and it cannot set a password, mint a token, delete a role or
 * create an account. Those are the operations where a mistaken tool call is not a mistake you
 * can review afterwards.
 */
final class AuthModule extends AbstractModule implements HasNavSection, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'admins';

    /** Who reads the roles: the same as `GET auth/roles`. */
    private const ROLE_READERS = ['admins.view', 'admins.manage', 'roles.manage', 'roles.assign'];

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-auth::module.title');
    }

    public function icon(): string
    {
        return 'users';
    }

    public function order(): int
    {
        return 900;
    }

    public function group(): string
    {
        return 'system';
    }

    public function navSection(): string
    {
        return SystemSections::ACCESS;
    }

    /**
     * Access is managed apart from the modules and handed out apart (WEBX_UI_PERMISSIONS.md §6.1):
     * the accounts, the roles, and who holds which are three permissions, not one, and none of
     * them comes with any other.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['admins.view', 'admins.manage', 'admins.audit', 'roles.manage', 'roles.assign'];
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return ['roles' => true, 'loginLog' => true];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return [
            Tool::read(
                'list_admins',
                'List the administrators of this panel with their roles and whether they are active.',
                static fn (): array => CmsUser::query()
                    ->with('roles')
                    ->orderBy('name')
                    ->get()
                    ->map(static fn (CmsUser $user): array => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_super' => $user->is_super,
                        'is_active' => $user->is_active,
                        'roles' => $user->roles->pluck('slug')->all(),
                        'last_login_at' => $user->last_login_at?->toIso8601String(),
                    ])
                    ->all(),
                scope: 'admins:read',
            ),

            Tool::read(
                'list_roles',
                'List the roles, the permissions each one grants and who holds it.',
                static fn (): array => Role::query()
                    ->with('users')
                    ->orderBy('slug')
                    ->get()
                    ->map(static fn (Role $role): array => [
                        'slug' => $role->slug,
                        'name' => $role->name,
                        'permissions' => $role->permissions ?? [],
                        'users' => $role->users->sortBy('email')->pluck('email')->values()->all(),
                    ])
                    ->all(),
                scope: 'admins:read',
                // Whoever reads the roles in the panel: the people's managers and the roles' own.
                permission: self::ROLE_READERS,
            ),

            Tool::read(
                'list_permissions',
                'Every permission a role may grant, by module, and whether you hold it — a role gets only what you hold.',
                static fn (array $arguments, ?Authenticatable $user = null): array => Container::getInstance()
                    ->make(PermissionMatrix::class)
                    ->for($user),
                scope: 'admins:read',
                permission: self::ROLE_READERS,
            ),

            Tool::mutating(
                'create_role',
                'Make a role: a name and the permissions it grants (see list_permissions). Nobody holds it until it is granted.',
                static fn (array $arguments, ?Authenticatable $user = null): array => self::saveRole($arguments, $user, creating: true),
                [
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'What the panel calls the role'],
                        'slug' => ['type' => 'string', 'description' => 'Its code: lowercase letters, digits and dashes; made from the name when left out'],
                        'permissions' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Permissions it grants, e.g. pages.manage'],
                    ],
                    'required' => ['name', 'permissions'],
                ],
                scope: 'admins:write',
                permission: 'roles.manage',
            ),

            Tool::mutating(
                'update_role',
                'Rename a role or change what it grants: the whole list in permissions, or add and remove. Everybody who holds it gains or loses at once.',
                static fn (array $arguments, ?Authenticatable $user = null): array => self::saveRole($arguments, $user, creating: false),
                [
                    'properties' => [
                        'role' => ['type' => 'string', 'description' => 'Role slug'],
                        'name' => ['type' => 'string', 'description' => 'A new name'],
                        'permissions' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Every permission it should grant, replacing the list'],
                        'add' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Permissions to add'],
                        'remove' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Permissions to take away'],
                    ],
                    'required' => ['role'],
                ],
                scope: 'admins:write',
                permission: 'roles.manage',
            ),

            Tool::mutating(
                'grant_role',
                'Give an administrator a role.',
                static fn (array $arguments, ?Authenticatable $user = null): array => self::changeRole($arguments, $user, attach: true),
                [
                    'properties' => [
                        'email' => ['type' => 'string', 'description' => 'Who to change'],
                        'role' => ['type' => 'string', 'description' => 'Role slug'],
                    ],
                    'required' => ['email', 'role'],
                ],
                scope: 'admins:write',
                permission: 'roles.assign',
            ),

            Tool::mutating(
                'revoke_role',
                'Take a role away from an administrator.',
                static fn (array $arguments, ?Authenticatable $user = null): array => self::changeRole($arguments, $user, attach: false),
                [
                    'properties' => [
                        'email' => ['type' => 'string', 'description' => 'Who to change'],
                        'role' => ['type' => 'string', 'description' => 'Role slug'],
                    ],
                    'required' => ['email', 'role'],
                ],
                scope: 'admins:write',
                permission: 'roles.assign',
            ),

            Tool::read(
                'recent_sign_ins',
                'The most recent sign-in attempts, successful and failed.',
                static fn (array $arguments): array => LoginRecord::query()
                    ->with('user')
                    ->latest('created_at')
                    ->limit(min((int) ($arguments['limit'] ?? 50), 200))
                    ->get()
                    ->map(static fn (LoginRecord $record): array => [
                        'email' => $record->email,
                        'successful' => $record->successful,
                        'ip' => $record->ip,
                        'at' => $record->created_at?->toIso8601String(),
                    ])
                    ->all(),
                [
                    'properties' => [
                        'limit' => ['type' => 'integer', 'default' => 50, 'maximum' => 200],
                    ],
                ],
                scope: 'admins:audit',
                permission: 'admins.audit',
            ),

            Tool::read(
                'failed_sign_in_bursts',
                'Addresses with repeated failed sign-ins recently — the shape of somebody guessing.',
                static fn (array $arguments): array => self::bursts($arguments),
                [
                    'properties' => [
                        'hours' => ['type' => 'integer', 'default' => 24],
                        'threshold' => ['type' => 'integer', 'default' => 5],
                    ],
                ],
                scope: 'admins:audit',
                permission: 'admins.audit',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function changeRole(array $arguments, ?Authenticatable $user, bool $attach): array
    {
        $email = (string) ($arguments['email'] ?? '');
        $slug = (string) ($arguments['role'] ?? '');
        $dryRun = (bool) ($arguments['dry_run'] ?? false);

        $target = CmsUser::query()->where('email', $email)->first();
        $role = Role::query()->where('slug', $slug)->first();

        if (! $target instanceof CmsUser) {
            return ['ok' => false, 'reason' => "No administrator with the address [{$email}]."];
        }

        if (! $role instanceof Role) {
            return ['ok' => false, 'reason' => "No role with the slug [{$slug}]."];
        }

        $had = $target->roles()->whereKey($role->getKey())->exists();
        $changes = $attach !== $had;

        // The same rules as the form in the panel, before anything is shown as possible: a dry
        // run that says yes to what the call would be refused is a lie told in advance.
        $rules = Container::getInstance()->make(AccessRules::class);
        $refusal = $changes
            ? $rules->changeRoles($user instanceof CmsUser ? $user : null, $target, $attach ? [$role] : [])
            : null;

        if ($refusal !== null) {
            return ['ok' => false, 'rule' => $refusal->rule, 'reason' => $refusal->message(), 'missing' => $refusal->missing];
        }

        if ($dryRun || ! $changes) {
            return [
                'ok' => true,
                'would_change' => $changes,
                'user' => $target->email,
                'role' => $role->slug,
                'applied' => false,
            ];
        }

        $before = $target->roles()->pluck('slug')->map(static fn (mixed $slug): string => (string) $slug)->all();

        $attach
            ? $target->roles()->attach($role->getKey())
            : $target->roles()->detach($role->getKey());

        $rules->recordRoles($target, $before, $target->roles()->pluck('slug')->map(static fn (mixed $slug): string => (string) $slug)->all());

        return [
            'ok' => true,
            'would_change' => true,
            'user' => $target->email,
            'role' => $role->slug,
            'applied' => true,
        ];
    }

    /**
     * A new role, or an existing one renamed or given other permissions — validated as the form
     * is ({@see RoleRequest}), and under the same rules: a role gains only what its editor holds
     * (rule 2), and a role the editor holds is not theirs to edit (rule 5). A dry run asks both.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function saveRole(array $arguments, ?Authenticatable $user, bool $creating): array
    {
        $dryRun = (bool) ($arguments['dry_run'] ?? false);
        $role = null;

        if (! $creating) {
            $slug = (string) ($arguments['role'] ?? '');
            $role = Role::query()->where('slug', $slug)->first();

            if (! $role instanceof Role) {
                return ['ok' => false, 'reason' => "No role with the slug [{$slug}]."];
            }
        }

        $strings = static fn (mixed $list): array => array_values(array_map('strval', (array) $list));
        $before = $role->permissions ?? [];
        $permissions = array_key_exists('permissions', $arguments) ? $strings($arguments['permissions']) : $before;
        $permissions = array_merge($permissions, $strings($arguments['add'] ?? []));
        $permissions = array_values(array_unique(array_diff($permissions, $strings($arguments['remove'] ?? []))));

        $input = ['permissions' => $permissions];

        foreach (['name', 'slug'] as $field) {
            if (array_key_exists($field, $arguments) && ($creating || $field === 'name')) {
                $input[$field] = $arguments[$field];
            }
        }

        Validator::make($input, RoleRequest::rulesFor($role), [], RoleRequest::names())->validate();

        $rules = Container::getInstance()->make(AccessRules::class);
        $refusal = $rules->saveRole($user instanceof CmsUser ? $user : null, $role, $permissions);

        if ($refusal !== null) {
            return ['ok' => false, 'rule' => $refusal->rule, 'reason' => $refusal->message(), 'missing' => $refusal->missing];
        }

        $name = (string) ($input['name'] ?? $role?->name);
        $gained = array_values(array_diff($permissions, $before));
        $lost = array_values(array_diff($before, $permissions));
        $changes = $creating || $gained !== [] || $lost !== [] || $name !== $role?->name;

        $answer = [
            'ok' => true,
            'would_change' => $changes,
            'role' => $role->slug ?? (isset($input['slug']) && $input['slug'] !== '' ? (string) $input['slug'] : Role::freeSlug($name)),
            'name' => $name,
            'gained' => $gained,
            'lost' => $lost,
            'applied' => false,
        ];

        if ($dryRun || ! $changes) {
            return $answer;
        }

        $role ??= new Role(['slug' => $answer['role']]);
        $role->name = $name;
        $role->permissions = $permissions;
        $role->save();

        return ['applied' => true] + $answer;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return list<array<string, mixed>>
     */
    private static function bursts(array $arguments): array
    {
        $hours = max(1, (int) ($arguments['hours'] ?? 24));
        $threshold = max(1, (int) ($arguments['threshold'] ?? 5));

        // The base builder rather than the model: these rows are an aggregate, not login
        // records, and pretending otherwise gives them properties the model does not have.
        return (new LoginRecord)->newQuery()->toBase()
            ->selectRaw('email, count(*) as attempts, max(created_at) as last_attempt')
            ->where('successful', false)
            ->where('created_at', '>=', Carbon::now()->subHours($hours))
            ->groupBy('email')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->orderByDesc('attempts')
            ->get()
            ->map(static fn ($row): array => [
                'email' => (string) $row->email,
                'attempts' => (int) $row->attempts,
                'last_attempt' => (string) $row->last_attempt,
            ])
            ->all();
    }
}
