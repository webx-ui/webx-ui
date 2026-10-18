<?php

declare(strict_types=1);

namespace WebxUi\Auth\Access;

use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Admin\History\Journal;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;

/**
 * What nobody but a super administrator may do to access, even holding the permission for it
 * (WEBX_UI_PERMISSIONS.md §6.2). One class for the panel's routes, the MCP tools and the keys,
 * so that the rules cannot drift between doors:
 *
 * 1. your own roles are not yours to change;
 * 2. nobody hands out — in a role, an assignment or a key — a permission they do not hold, nor
 *    takes over an account that holds more than they do;
 * 3. only a super administrator makes or unmakes one;
 * 4. only a super administrator touches one;
 * 5. a role you hold is not yours to edit;
 * 6. the last active super administrator stays one;
 * 8. every refusal is a 403 with the rule, and a row in the journal.
 *
 * A null actor is the console: whoever runs artisan already has the server, and is not asked.
 * Each method answers null for "go ahead", or the refusal — already written down.
 */
final class AccessRules
{
    public function __construct(
        private readonly AccessPolicy $policy,
        private readonly Journal $journal,
    ) {}

    /**
     * @param  list<int>  $roles  the ids of the roles the new account would have
     */
    public function create(?CmsUser $actor, bool $super, array $roles): ?Refusal
    {
        if ($actor === null || $actor->is_super) {
            return null;
        }

        if ($super) {
            return $this->refuse($actor, null, new Refusal(Refusal::ONLY_SUPER), 'create');
        }

        if ($roles === []) {
            return null;
        }

        return $this->assigning($actor, null, $this->roles($roles), 'create');
    }

    /**
     * An edit of somebody's account. `$changes` holds only what the request sent, by column —
     * `roles` as a list of role ids — so that a field left out is a field left alone.
     *
     * @param  array<string, mixed>  $changes
     */
    public function edit(?CmsUser $actor, CmsUser $target, array $changes): ?Refusal
    {
        $self = $actor !== null && $actor->is($target);
        $deactivating = array_key_exists('is_active', $changes) && ! $changes['is_active'] && $target->is_active;
        $unmaking = array_key_exists('is_super', $changes) && ! $changes['is_super'] && $target->is_super;

        if ($self && $deactivating) {
            return $this->refuse($actor, $target, new Refusal(Refusal::NOT_YOURSELF), 'deactivate');
        }

        if (($deactivating || $unmaking) && $target->is_super && $this->lastActiveSuper($target)) {
            return $this->refuse($actor, $target, new Refusal(Refusal::LAST_SUPER), $unmaking ? 'unmake-super' : 'deactivate');
        }

        if ($actor === null || $actor->is_super) {
            return null;
        }

        // Rules 3 and 4 before anything else: a super administrator is nobody else's to touch,
        // and a tick on «Super administrator» is every permission there is.
        if ($target->is_super || (array_key_exists('is_super', $changes) && (bool) $changes['is_super'])) {
            return $this->refuse($actor, $target, new Refusal(Refusal::ONLY_SUPER), 'edit');
        }

        $account = array_diff(array_keys($changes), ['roles']);

        // Rule 2 for the account itself: a new address and password on somebody who holds more
        // than you is their access in your hands.
        if ($account !== [] && ! $self && ! $this->policy->covers($actor, $target)) {
            return $this->refuse($actor, $target, new Refusal(Refusal::BEYOND_YOURS, $this->policy->missing($actor, $target->grantedPermissions())), 'edit');
        }

        if (! array_key_exists('roles', $changes)) {
            return null;
        }

        /** @var list<int> $wanted */
        $wanted = array_values(array_map('intval', (array) $changes['roles']));
        $held = $target->roles()->pluck('cms_roles.id')->map(static fn (mixed $id): int => (int) $id)->all();

        if (array_diff($wanted, $held) === [] && array_diff($held, $wanted) === []) {
            return null;
        }

        return $this->assigning($actor, $target, $this->roles(array_values(array_diff($wanted, $held))), 'roles');
    }

    public function delete(?CmsUser $actor, CmsUser $target): ?Refusal
    {
        if ($actor !== null && $actor->is($target)) {
            return $this->refuse($actor, $target, new Refusal(Refusal::NOT_YOURSELF), 'delete');
        }

        if ($actor !== null && ! $actor->is_super && $target->is_super) {
            return $this->refuse($actor, $target, new Refusal(Refusal::ONLY_SUPER), 'delete');
        }

        if ($target->is_super && $this->lastActiveSuper($target)) {
            return $this->refuse($actor, $target, new Refusal(Refusal::LAST_SUPER), 'delete');
        }

        if ($actor !== null && ! $this->policy->covers($actor, $target)) {
            return $this->refuse($actor, $target, new Refusal(Refusal::BEYOND_YOURS, $this->policy->missing($actor, $target->grantedPermissions())), 'delete');
        }

        return null;
    }

    /**
     * Giving somebody roles and taking them away. Taking away asks less than giving: it hands
     * out nothing, so only rules 1 and 4 stand in its way.
     *
     * @param  list<Role>  $adding
     */
    public function changeRoles(?CmsUser $actor, CmsUser $target, array $adding): ?Refusal
    {
        if ($actor === null || $actor->is_super) {
            return null;
        }

        if ($target->is_super) {
            return $this->refuse($actor, $target, new Refusal(Refusal::ONLY_SUPER), 'roles');
        }

        return $this->assigning($actor, $target, $adding, 'roles');
    }

    /**
     * A new role, or an existing one given these permissions.
     *
     * @param  list<string>  $permissions
     */
    public function saveRole(?CmsUser $actor, ?Role $role, array $permissions): ?Refusal
    {
        if ($actor === null || $actor->is_super) {
            return null;
        }

        if (! $this->policy->allows($actor, 'roles.manage')) {
            return $this->refuse($actor, $role, new Refusal(Refusal::FORBIDDEN, ['roles.manage']), 'role');
        }

        if ($role !== null && $this->holdsRole($actor, $role)) {
            return $this->refuse($actor, $role, new Refusal(Refusal::ROLE_YOU_HOLD), 'role');
        }

        // What the role already had is not handed out by this edit; what it gains is.
        $gained = array_values(array_diff($permissions, $role->permissions ?? []));
        $missing = $this->policy->missing($actor, $gained);

        if ($missing !== []) {
            return $this->refuse($actor, $role, new Refusal(Refusal::BEYOND_YOURS, $missing), 'role');
        }

        return null;
    }

    public function deleteRole(?CmsUser $actor, Role $role): ?Refusal
    {
        if ($actor === null || $actor->is_super) {
            return null;
        }

        if (! $this->policy->allows($actor, 'roles.manage')) {
            return $this->refuse($actor, $role, new Refusal(Refusal::FORBIDDEN, ['roles.manage']), 'delete-role');
        }

        if ($this->holdsRole($actor, $role)) {
            return $this->refuse($actor, $role, new Refusal(Refusal::ROLE_YOU_HOLD), 'delete-role');
        }

        return null;
    }

    /**
     * A key acts as the person it names (rule 7). Your own needs nothing — it is no more than you
     * already are; somebody else's is speaking for them, which is `admins.manage`, and one for a
     * super administrator, or for somebody holding more than the issuer, is more than the issuer
     * holds.
     */
    public function issueKeyFor(?CmsUser $actor, CmsUser $owner): ?Refusal
    {
        if ($actor === null || $actor->is_super || $actor->is($owner)) {
            return null;
        }

        if (! $this->policy->allows($actor, 'admins.manage')) {
            return $this->refuse($actor, $owner, new Refusal(Refusal::FORBIDDEN, ['admins.manage']), 'key');
        }

        if ($owner->is_super) {
            return $this->refuse($actor, $owner, new Refusal(Refusal::ONLY_SUPER), 'key');
        }

        if (! $this->policy->covers($actor, $owner)) {
            return $this->refuse($actor, $owner, new Refusal(Refusal::BEYOND_YOURS, $this->policy->missing($actor, $owner->grantedPermissions())), 'key');
        }

        return null;
    }

    /**
     * Switching a key off hands nothing out, so taking one away from a program is open wider than
     * issuing: your own always, anybody's with `admins.manage` — except a super administrator's,
     * which is theirs or another super administrator's to switch off (§7).
     */
    public function revokeKeyOf(?CmsUser $actor, ?CmsUser $owner): ?Refusal
    {
        if ($actor === null || $actor->is_super || ($owner !== null && $actor->is($owner))) {
            return null;
        }

        if (! $this->policy->allows($actor, 'admins.manage')) {
            return $this->refuse($actor, $owner, new Refusal(Refusal::FORBIDDEN, ['admins.manage']), 'revoke-key');
        }

        if ($owner !== null && $owner->is_super) {
            return $this->refuse($actor, $owner, new Refusal(Refusal::ONLY_SUPER), 'revoke-key');
        }

        return null;
    }

    /**
     * Who holds which roles, before and after, as one row on the account — the assignment is not
     * a model of its own, so the journal hears of it from here.
     *
     * @param  list<string>  $before  role slugs
     * @param  list<string>  $after
     */
    public function recordRoles(CmsUser $target, array $before, array $after): void
    {
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        $this->journal->record($target, 'updated', [
            ['field' => 'roles', 'from' => $before, 'to' => $after],
        ]);
    }

    /** That a password was changed — the fact, never the value. */
    public function recordPassword(CmsUser $target): void
    {
        $this->journal->record($target, 'updated', [
            ['field' => 'password', 'from' => '••••', 'to' => '••••', 'masked' => true],
        ]);
    }

    /**
     * Rules 1 and 2 for an assignment: not to yourself, and nothing you do not hold.
     *
     * @param  list<Role>  $adding
     */
    private function assigning(CmsUser $actor, ?CmsUser $target, array $adding, string $attempt): ?Refusal
    {
        if (! $this->policy->allows($actor, 'roles.assign')) {
            return $this->refuse($actor, $target, new Refusal(Refusal::FORBIDDEN, ['roles.assign']), $attempt);
        }

        if ($target !== null && $actor->is($target)) {
            return $this->refuse($actor, $target, new Refusal(Refusal::OWN_ROLES), $attempt);
        }

        $granting = [];

        foreach ($adding as $role) {
            array_push($granting, ...($role->permissions ?? []));
        }

        $missing = $this->policy->missing($actor, $granting);

        if ($missing !== []) {
            return $this->refuse($actor, $target, new Refusal(Refusal::BEYOND_YOURS, $missing), $attempt);
        }

        return null;
    }

    /**
     * Rule 8: written down whoever is refused — on the record they reached for, or on their own
     * account when there was none yet.
     */
    private function refuse(?CmsUser $actor, CmsUser|Role|null $subject, Refusal $refusal, string $attempt): Refusal
    {
        $subject ??= $actor;

        if ($subject !== null) {
            $this->journal->record($subject, 'refused', [
                ['field' => 'refused', 'from' => $attempt, 'to' => $refusal->rule],
            ]);
        }

        return $refusal;
    }

    private function lastActiveSuper(CmsUser $target): bool
    {
        return ! CmsUser::query()
            ->where('is_super', true)
            ->where('is_active', true)
            ->whereKeyNot($target->getKey())
            ->exists();
    }

    private function holdsRole(CmsUser $actor, Role $role): bool
    {
        return $actor->roles()->whereKey($role->getKey())->exists();
    }

    /**
     * @param  list<int>  $ids
     * @return list<Role>
     */
    private function roles(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values(Role::query()->whereKey($ids)->get()->all());
    }
}
