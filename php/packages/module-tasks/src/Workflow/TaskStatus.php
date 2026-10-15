<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

/**
 * Where a task is (§3 of the spec).
 *
 * Only `draft` and `cancelled` are ever set; every other status is read off the stages by
 * {@see self::of()}, so the task cannot say one thing while its stages say another.
 */
enum TaskStatus: string
{
    case Draft = 'draft';

    case Ready = 'ready';

    case InProgress = 'in_progress';

    case Review = 'review';

    case Done = 'done';

    case Cancelled = 'cancelled';

    /**
     * The status the stages add up to.
     *
     * A blocked stage counts as work in progress, which §3 does not say: the work is started and
     * waits for a person, and calling the task «ready» would tell a reader that nobody has
     * touched it. Cancelled stages are not counted at all — a task whose remaining stages are all
     * done is done.
     *
     * @param  iterable<StageStatus>  $stages
     */
    public static function of(iterable $stages): self
    {
        $open = [];

        foreach ($stages as $status) {
            if ($status !== StageStatus::Cancelled) {
                $open[] = $status;
            }
        }

        $any = static fn (StageStatus ...$statuses): bool => array_intersect(
            array_map(static fn (StageStatus $status): string => $status->value, $open),
            array_map(static fn (StageStatus $status): string => $status->value, $statuses),
        ) !== [];

        if ($any(StageStatus::InProgress, StageStatus::Rework, StageStatus::Blocked)) {
            return self::InProgress;
        }

        if ($open !== [] && ! $any(StageStatus::Pending, StageStatus::Ready, StageStatus::Review)) {
            return self::Done;
        }

        if ($any(StageStatus::Review)) {
            return self::Review;
        }

        return self::Ready;
    }

    /** Over: neither done nor cancelled takes another change. */
    public function isClosed(): bool
    {
        return $this === self::Done || $this === self::Cancelled;
    }
}
