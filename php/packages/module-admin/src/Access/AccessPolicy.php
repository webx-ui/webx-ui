<?php

declare(strict_types=1);

namespace WebxUi\Admin\Access;

use Closure;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\ModuleRegistry;

/**
 * The one place that says whether somebody may do something (WEBX_UI_PERMISSIONS.md §4).
 *
 * Every door asks here — `cms.can` on a route, a tool at the MCP server, the journal, a link
 * picker, `hasPermission()` on the administrator — so the rules are written once:
 *
 * - a super administrator passes every check;
 * - otherwise the roles of the account are joined, and a permission is held when any role
 *   lists it, by its exact name; nothing that is not granted is allowed;
 * - a call made with a token is limited twice — by what the person holds and by the scopes the
 *   token carries ({@see self::decideCall()}): a token is never more than its owner.
 *
 * The record is the door left open for limits by record (§10: "only their own articles") that
 * are not made yet. Doors that have the record at hand pass it already; a constraint
 * registered later narrows the answer without any door changing.
 */
final class AccessPolicy
{
    /** @var list<Closure(HasPermissions, string, object): bool> */
    private array $constraints = [];

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @param  string|list<string>  $permissions  several mean any of them, the way `cms.can:a,b` does
     */
    public function allows(mixed $user, string|array $permissions, ?object $record = null): bool
    {
        return $this->decide($user, $permissions, $record)->allowed;
    }

    /**
     * @param  string|list<string>  $permissions  several mean any of them
     */
    public function decide(mixed $user, string|array $permissions, ?object $record = null): Decision
    {
        $wanted = self::names($permissions);

        if (! $user instanceof HasPermissions) {
            return Decision::deny(Decision::UNAUTHENTICATED, $wanted);
        }

        if ($this->isSuper($user)) {
            return Decision::allow();
        }

        foreach ($wanted as $permission) {
            if ($this->holds($user, $permission) && $this->recordAllows($user, $permission, $record)) {
                return Decision::allow();
            }
        }

        return Decision::deny(Decision::FORBIDDEN, $wanted);
    }

    /**
     * A call that came with a token: the token's scopes, then the person behind it. Either one
     * short is a no, which is what makes a token the intersection of the two.
     *
     * @param  list<string>  $permissions  any of which the person has to hold
     * @param  list<string>  $scopes  any of which the token has to carry
     */
    public function decideCall(mixed $user, array $permissions, array $scopes, ?object $record = null): Decision
    {
        if (! $this->tokenAllows($user, $scopes)) {
            return Decision::deny(Decision::TOKEN, array_values($scopes));
        }

        // Somebody the panel does not know as an administrator — the local stdio server, a
        // door that let its own kind of user in — is not ours to refuse: whoever let them in
        // decides, the same as the token check above says for a call without a token.
        if (! $user instanceof HasPermissions) {
            return Decision::allow();
        }

        return $this->decide($user, $permissions, $record);
    }

    /**
     * Whether the token a call came with carries any of these scopes.
     *
     * A caller without a token is not refused here: either there is no caller, or a person is
     * signed in through a session and the permission check is the one that limits them.
     *
     * @param  list<string>  $scopes
     */
    public function tokenAllows(mixed $user, array $scopes): bool
    {
        if (! is_object($user) || ! method_exists($user, 'tokenCan') || self::tokenOf($user) === null) {
            return true;
        }

        foreach ($scopes as $scope) {
            if ($user->tokenCan($scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The token the call came in with, whatever the model calls it.
     *
     * Passport names it both ways and answers null for a user who arrived by session; asking
     * `tokenCan()` alone would not tell the two apart.
     */
    public static function tokenOf(object $user): ?object
    {
        foreach (['currentAccessToken', 'token'] as $method) {
            if (! method_exists($user, $method)) {
                continue;
            }

            $token = $user->{$method}();

            if (is_object($token)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Whether this account may see what a `view` permission guards in a picker — a link, a
     * collection, a relation: the permission itself, or the `manage` of the same family.
     *
     * Choice 1(a) of the permissions spec: `manage` does not carry `view` in by itself, and every
     * place that needs both names both. A route does it with `cms.can:x.view,x.manage`; a picker
     * is given one permission by the module that registers it, so the second is named here,
     * once, for all three. Somebody who edits the services and cannot pick a service to link to
     * is not being protected from anything.
     */
    public function sees(mixed $user, string $permission): bool
    {
        return $this->allows($user, self::seeing($permission));
    }

    /**
     * `services.view` → `services.view`, `services.manage`; anything else stays as it is.
     *
     * @return list<string>
     */
    public static function seeing(string $permission): array
    {
        return str_ends_with($permission, '.view')
            ? [$permission, substr($permission, 0, -strlen('.view')).'.manage']
            : [$permission];
    }

    public function isSuper(mixed $user): bool
    {
        return $user instanceof HoldsPermissions && $user->isSuperAdministrator();
    }

    /**
     * Whether the account holds this one permission, by its roles. An account that cannot list
     * what it was granted is asked directly.
     */
    public function holds(HasPermissions $user, string $permission): bool
    {
        if ($user instanceof HoldsPermissions) {
            return $user->isSuperAdministrator() || in_array($permission, $user->grantedPermissions(), true);
        }

        return $user->hasPermission($permission);
    }

    /**
     * Of these permissions, the ones the account does not hold — what "you may not hand out
     * more than you have" refuses over (§6.2, rule 2). Empty for a super administrator.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public function missing(mixed $user, array $permissions): array
    {
        if ($this->isSuper($user)) {
            return [];
        }

        if (! $user instanceof HasPermissions) {
            return array_values(array_unique($permissions));
        }

        return array_values(array_unique(array_filter(
            $permissions,
            fn (string $permission): bool => ! $this->holds($user, $permission),
        )));
    }

    /**
     * Whether one account holds everything another does. A super administrator is covered only
     * by another one.
     */
    public function covers(mixed $user, HoldsPermissions $other): bool
    {
        if ($this->isSuper($user)) {
            return true;
        }

        if ($other->isSuperAdministrator()) {
            return false;
        }

        return $this->missing($user, $other->grantedPermissions()) === [];
    }

    /**
     * A limit by record, for the day there is one (§10). It is asked only when a door passed the
     * record, after the permission itself is held; false is a no.
     *
     * @param  Closure(HasPermissions, string, object): bool  $constraint
     */
    public function constrain(Closure $constraint): void
    {
        $this->constraints[] = $constraint;
    }

    /**
     * Every permission an installed module declares, sorted — what a role may list.
     *
     * @return list<string>
     */
    public function declared(): array
    {
        $all = [];

        foreach ($this->modules->all() as $module) {
            foreach ($module->permissions() as $permission) {
                $all[$permission] = true;
            }
        }

        $names = array_map('strval', array_keys($all));
        sort($names);

        return $names;
    }

    public function isDeclared(string $permission): bool
    {
        return in_array($permission, $this->declared(), true);
    }

    private function recordAllows(HasPermissions $user, string $permission, ?object $record): bool
    {
        if ($record === null) {
            return true;
        }

        foreach ($this->constraints as $constraint) {
            if (! $constraint($user, $permission, $record)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  string|list<string>  $permissions
     * @return list<string>
     */
    private static function names(string|array $permissions): array
    {
        return array_values(array_filter(
            array_map('trim', (array) $permissions),
            static fn (string $permission): bool => $permission !== '',
        ));
    }
}
