<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

/**
 * Where a stage is (§3 of the spec).
 *
 * The moves between them are not methods here: each one belongs to an operation of
 * {@see Tasks}, which knows who is asking and what else changes with it. This only says which
 * moves exist at all, so that no operation can invent one.
 */
enum StageStatus: string
{
    /** Waiting for the stages before it. */
    case Pending = 'pending';

    /** Free to take. */
    case Ready = 'ready';

    /** Taken by an agent. */
    case InProgress = 'in_progress';

    /** Reported, waiting for a reviewer. */
    case Review = 'review';

    /** Sent back with remarks; taken before any ready stage. */
    case Rework = 'rework';

    /** The agent asked a person something and cannot go on until they answer. */
    case Blocked = 'blocked';

    case Done = 'done';

    case Cancelled = 'cancelled';

    /**
     * Whether this stage may become `$to`. Cancelling is allowed from anywhere but done.
     */
    public function canBecome(self $to): bool
    {
        if ($to === self::Cancelled) {
            return $this !== self::Done && $this !== self::Cancelled;
        }

        return in_array($to, match ($this) {
            self::Pending => [self::Ready],
            // Back to pending only when a reorder puts another stage in front of it.
            self::Ready => [self::InProgress, self::Pending],
            self::InProgress => [self::Review, self::Blocked, self::Ready, self::Rework],
            self::Review => [self::Done, self::Rework],
            self::Rework => [self::InProgress],
            self::Blocked => [self::Ready],
            self::Done, self::Cancelled => [],
        }, true);
    }

    /** What an agent may take: rework first, then ready. */
    public function isTakeable(): bool
    {
        return $this === self::Ready || $this === self::Rework;
    }

    /** Settled for good: nothing happens to it any more, and the stage after it may start. */
    public function isClosed(): bool
    {
        return $this === self::Done || $this === self::Cancelled;
    }

    /** Not started yet, so a person may still rewrite it, move it or drop it (§3). */
    public function isEditable(): bool
    {
        return $this === self::Pending || $this === self::Ready;
    }
}
