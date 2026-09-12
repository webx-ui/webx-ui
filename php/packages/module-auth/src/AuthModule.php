<?php

declare(strict_types=1);

namespace WebxUi\Auth;

use Illuminate\Support\Carbon;
use WebxUi\Admin\AbstractModule;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\LoginRecord;
use WebxUi\Auth\Models\Role;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The panel section for administrators and roles.
 *
 * Its MCP tools stop where they should: an agent can see who has what and move people between
 * roles, and it cannot set a password, mint a token or create an account. Those are the
 * operations where a mistaken tool call is not a mistake you can review afterwards.
 */
final class AuthModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function id(): string
    {
        return 'users';
    }

    public function title(): string
    {
        return 'Administrators';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function order(): int
    {
        return 900;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['users.view', 'users.manage', 'users.audit'];
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
                'list_users',
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
                scope: 'users:read',
            ),

            Tool::read(
                'list_roles',
                'List the roles and the permissions each one grants.',
                static fn (): array => Role::query()
                    ->orderBy('slug')
                    ->get()
                    ->map(static fn (Role $role): array => [
                        'slug' => $role->slug,
                        'name' => $role->name,
                        'permissions' => $role->permissions ?? [],
                    ])
                    ->all(),
                scope: 'users:read',
            ),

            Tool::mutating(
                'grant_role',
                'Give an administrator a role.',
                static fn (array $arguments): array => self::changeRole($arguments, attach: true),
                [
                    'properties' => [
                        'email' => ['type' => 'string', 'description' => 'Who to change'],
                        'role' => ['type' => 'string', 'description' => 'Role slug'],
                    ],
                    'required' => ['email', 'role'],
                ],
                scope: 'users:write',
            ),

            Tool::mutating(
                'revoke_role',
                'Take a role away from an administrator.',
                static fn (array $arguments): array => self::changeRole($arguments, attach: false),
                [
                    'properties' => [
                        'email' => ['type' => 'string', 'description' => 'Who to change'],
                        'role' => ['type' => 'string', 'description' => 'Role slug'],
                    ],
                    'required' => ['email', 'role'],
                ],
                scope: 'users:write',
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
                scope: 'users:audit',
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
                scope: 'users:audit',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function changeRole(array $arguments, bool $attach): array
    {
        $email = (string) ($arguments['email'] ?? '');
        $slug = (string) ($arguments['role'] ?? '');
        $dryRun = (bool) ($arguments['dry_run'] ?? false);

        $user = CmsUser::query()->where('email', $email)->first();
        $role = Role::query()->where('slug', $slug)->first();

        if (! $user instanceof CmsUser) {
            return ['ok' => false, 'reason' => "No administrator with the address [{$email}]."];
        }

        if (! $role instanceof Role) {
            return ['ok' => false, 'reason' => "No role with the slug [{$slug}]."];
        }

        $had = $user->roles()->whereKey($role->getKey())->exists();
        $changes = $attach !== $had;

        if ($dryRun || ! $changes) {
            return [
                'ok' => true,
                'would_change' => $changes,
                'user' => $user->email,
                'role' => $role->slug,
                'applied' => false,
            ];
        }

        $attach
            ? $user->roles()->attach($role->getKey())
            : $user->roles()->detach($role->getKey());

        return [
            'ok' => true,
            'would_change' => true,
            'user' => $user->email,
            'role' => $role->slug,
            'applied' => true,
        ];
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
