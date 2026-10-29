<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * A change of the statuses that would leave orders nowhere to go (spec §5.2, §5.6): nothing was
 * written, and `plan` is what a dry run would have answered.
 */
final class StatusRefused extends Refusal
{
    /** New orders come into this status — the settings name it, or it is the first of the set. */
    public const string INITIAL = 'initial';

    /** The last active status of a group with orders in it: they would have nowhere to go inside it. */
    public const string LAST_ACTIVE = 'last_active';

    /** Its public group is deleted: bring the group back, or choose another, first. */
    public const string PUBLIC_DELETED = 'public_deleted';

    /** A public group with statuses still alive in it: move them to another group first. */
    public const string HAS_ACTIVE = 'has_active';

    public function __construct(public readonly StatusPlan $plan)
    {
        parent::__construct((string) $plan->refusal, match ($plan->refusal) {
            self::INITIAL => 'New orders come into this status.',
            self::LAST_ACTIVE => 'The orders of its group would have no active status left to go to.',
            self::PUBLIC_DELETED => 'Its public group is deleted.',
            default => 'The public group still has statuses in it.',
        });
    }
}
