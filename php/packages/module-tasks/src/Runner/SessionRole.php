<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Runner;

use WebxUi\Tasks\Workflow\StageStatus;

/**
 * Who a key for one stage is for (§14.4): the session that does the stage, or the one that checks
 * it. Each is let into a handful of tools, only on its own stage, and loses the key when the stage
 * leaves the status it was issued in.
 */
enum SessionRole: string
{
    case Worker = 'worker';
    case Reviewer = 'reviewer';

    /** The status the stage is in while the key is good; leaving it revokes the key. */
    public function status(): StageStatus
    {
        return match ($this) {
            self::Worker => StageStatus::InProgress,
            self::Reviewer => StageStatus::Review,
        };
    }

    /**
     * The tools of this module the key reaches. Tools of other modules are limited by the scopes
     * the key carries, not by this list — a hosting module's tools for a worker included.
     *
     * @return list<string>
     */
    public function tools(): array
    {
        return match ($this) {
            self::Worker => ['tasks_get', 'tasks_stage_report', 'tasks_stage_block', 'tasks_comment'],
            self::Reviewer => ['tasks_get', 'tasks_stage_accept', 'tasks_stage_rework', 'tasks_comment'],
        };
    }

    /**
     * The scopes of this module the key is issued with; the site adds others in
     * `webx-tasks.runner.session_scopes`.
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        return match ($this) {
            self::Worker => ['tasks:read', 'tasks:work'],
            self::Reviewer => ['tasks:read', 'tasks:review'],
        };
    }
}
