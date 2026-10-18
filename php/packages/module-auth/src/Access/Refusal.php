<?php

declare(strict_types=1);

namespace WebxUi\Auth\Access;

use Illuminate\Http\JsonResponse;

/**
 * A no from {@see AccessRules}: which rule said it, and what was missing when that is the reason.
 *
 * Always a 403 (WEBX_UI_PERMISSIONS.md §6.2, rule 8): the request was understood and is not
 * this person's to make, whatever field it came in.
 */
final readonly class Refusal
{
    /** Missing the permission the action itself is behind — `roles.assign`, `roles.manage`. */
    public const FORBIDDEN = 'forbidden';

    /** Rule 1: your own roles are somebody else's to change. */
    public const OWN_ROLES = 'own-roles';

    /** Rule 2: handing out, or reaching, more than you hold. */
    public const BEYOND_YOURS = 'beyond-yours';

    /** Rules 3 and 4: making, unmaking or touching a super administrator. */
    public const ONLY_SUPER = 'only-super';

    /** Rule 5: a role you hold is a way to widen yourself. */
    public const ROLE_YOU_HOLD = 'role-you-hold';

    /** Rule 6: the last active super administrator stays one. */
    public const LAST_SUPER = 'last-super';

    /** Switching yourself off or deleting yourself. */
    public const NOT_YOURSELF = 'not-yourself';

    /**
     * @param  list<string>  $missing  the permissions that were missing, when that is the reason
     */
    public function __construct(
        public string $rule,
        public array $missing = [],
    ) {}

    public function message(): string
    {
        return (string) __('webx-auth::errors.'.$this->rule);
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse(array_filter([
            'message' => $this->message(),
            'rule' => $this->rule,
            'missing' => $this->missing,
        ], static fn (mixed $value): bool => $value !== []), 403);
    }
}
