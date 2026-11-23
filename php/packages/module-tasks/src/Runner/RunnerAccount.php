<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Runner;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Auth\Keys\IssuedKey;
use WebxUi\Auth\Keys\KeyIssuer;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;

/**
 * The watcher's own account (§14.1): the role `runner`, the administrator «taskd» who holds it,
 * and the key the watcher polls with.
 *
 * The role holds what the keys the watcher hands out need — doing a stage and reviewing one — and
 * the watcher's own key carries none of that: its scope is `tasks:run` (plus whatever the site
 * adds for admission), so the watcher takes stages, gives them back, reads its settings and beats,
 * but never reports or decides on a stage. A session does that with the key of its stage.
 */
final class RunnerAccount
{
    public const ROLE = 'runner';

    /** What the key of the watcher is called in the list of keys — and what a new one replaces. */
    public const KEY = 'taskd';

    public function __construct(
        private readonly KeyIssuer $issuer,
        private readonly AccessPolicy $policy,
        private readonly Config $config,
    ) {}

    /**
     * The role's permissions: the module's three and those the site lists, each only if a module
     * of the site declares it.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        $extra = $this->config->get('webx-tasks.runner.permissions', []);

        return array_values(array_filter(
            array_unique(['tasks.run', 'tasks.work', 'tasks.review', ...(is_array($extra) ? array_map(strval(...), $extra) : [])]),
            fn (string $permission): bool => $this->policy->isDeclared($permission),
        ));
    }

    /**
     * The scopes of the watcher's own key.
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        $extra = $this->config->get('webx-tasks.runner.scopes', []);

        return array_values(array_unique(['tasks:run', ...(is_array($extra) ? array_map(strval(...), $extra) : [])]));
    }

    /**
     * The role and the administrator, made if missing; a role there already is topped up with
     * what it lacks and keeps what a person added to it.
     */
    public function ensure(): CmsUser
    {
        $role = Role::query()->where('slug', self::ROLE)->first();

        if ($role === null) {
            $role = Role::query()->create([
                'slug' => self::ROLE,
                'name' => (string) __('webx-tasks::module.runner-role'),
                'permissions' => $this->permissions(),
            ]);
        } else {
            $missing = array_values(array_diff($this->permissions(), $role->permissions ?? []));

            if ($missing !== []) {
                $role->permissions = [...($role->permissions ?? []), ...$missing];
                $role->save();
            }
        }

        $email = (string) $this->config->get('webx-tasks.runner.email', 'taskd@runner.invalid');

        $user = CmsUser::query()->where('email', $email)->first() ?? CmsUser::query()->create([
            'name' => self::KEY,
            'email' => $email,
            // Nobody signs in as the watcher: the password is never shown, and the key is its door.
            'password' => Str::random(64),
            'is_super' => false,
            'is_active' => true,
        ]);

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }

    /** A new key for the watcher; the one it had stops working. */
    public function issue(int $days): IssuedKey
    {
        $user = $this->ensure();

        $this->issuer->revokeNamed($user, self::KEY);

        return $this->issuer->issue($user, self::KEY, $this->scopes(), $days);
    }
}
