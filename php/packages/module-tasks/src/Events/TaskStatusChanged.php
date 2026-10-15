<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Events;

use WebxUi\Tasks\Workflow\TaskStatus;

/** A task's status changed — set by hand or read off its stages; fired inside that transaction. */
final readonly class TaskStatusChanged
{
    public function __construct(
        public int $taskId,
        public ?TaskStatus $from,
        public TaskStatus $to,
    ) {}
}
