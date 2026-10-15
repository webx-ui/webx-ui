<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

/**
 * How soon a task's stages are handed out, after rework and before age.
 */
enum Priority: string
{
    case Low = 'low';

    case Normal = 'normal';

    case High = 'high';

    /** The place in the queue: lower goes first. */
    public function rank(): int
    {
        return match ($this) {
            self::High => 0,
            self::Normal => 1,
            self::Low => 2,
        };
    }
}
