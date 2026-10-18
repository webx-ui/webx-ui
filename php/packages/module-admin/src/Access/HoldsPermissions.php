<?php

declare(strict_types=1);

namespace WebxUi\Admin\Access;

use WebxUi\Admin\Contracts\HasPermissions;

/**
 * Somebody who can say what they were granted, not only answer yes or no.
 *
 * The auth module's administrator implements it, and {@see AccessPolicy} decides from it: the
 * account says what its roles list and whether it is a super administrator, and the policy is
 * the one place that turns that into an answer. Without the list the policy could not tell
 * whether one person holds everything another does — the question every rule against handing
 * out more than you have comes down to (WEBX_UI_PERMISSIONS.md §6).
 */
interface HoldsPermissions extends HasPermissions
{
    /** Passes every check: somebody has to be able to grant the first permission. */
    public function isSuperAdministrator(): bool;

    /**
     * What the roles of this account grant, joined — empty for a super administrator, who is
     * not limited by roles at all.
     *
     * @return list<string>
     */
    public function grantedPermissions(): array;
}
