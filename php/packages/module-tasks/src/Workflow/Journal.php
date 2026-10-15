<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

use WebxUi\Tasks\Models\JournalEntry;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;

/**
 * The one place a line is added to a task's journal, so every line names its author the same way.
 */
final class Journal
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function write(Task $task, ?Stage $stage, Actor $actor, JournalKind $kind, ?string $body = null, array $meta = []): JournalEntry
    {
        return JournalEntry::query()->create([
            'task_id' => $task->id,
            'stage_id' => $stage?->id,
            'actor_type' => $actor->type,
            'actor_name' => $actor->name,
            'kind' => $kind,
            'body' => $body,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
