<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;

/**
 * What a dispatcher looks at once a minute (§5, `tasks_queue`): every stage that is waiting for
 * somebody, and nothing else — no texts, no journal, one row per stage.
 *
 * Blocked stages are in it too, which §5 does not list: a dispatcher that does not see them
 * cannot tell a quiet queue from one where every task waits for a person.
 */
final class Queue
{
    /** The statuses a stage waits for somebody in, in the order the summary counts them. */
    public const array STATUSES = [
        StageStatus::Rework,
        StageStatus::Ready,
        StageStatus::InProgress,
        StageStatus::Review,
        StageStatus::Blocked,
    ];

    /**
     * @return array{counts: array<string, int>, stages: list<array<string, mixed>>}
     */
    public function summary(?Project $project = null): array
    {
        $stages = Stage::query()
            ->with('task.project')
            ->whereIn('status', array_map(static fn (StageStatus $status): string => $status->value, self::STATUSES))
            ->whereHas('task', static function (Builder $query) use ($project): void {
                /** @var Builder<Task> $query */
                $query->whereNotIn('status', [TaskStatus::Draft->value, TaskStatus::Cancelled->value])
                    ->when($project !== null, static fn (Builder $query) => $query->where('project_id', $project?->id))
                    ->whereHas('project', static fn (Builder $query) => $query->whereNull('archived_at'));
            })
            ->orderBy('status_changed_at')
            ->orderBy('id')
            ->get();

        $counts = [];

        foreach (self::STATUSES as $status) {
            $counts[$status->value] = $stages->where('status', $status)->count();
        }

        return [
            'counts' => $counts,
            'stages' => $stages->map(static fn (Stage $stage): array => [
                'stage_id' => $stage->id,
                'status' => $stage->status->value,
                'review_mode' => $stage->review_mode->value,
                'project' => $stage->task->project->slug,
                'task_id' => $stage->task_id,
                'task' => $stage->task->project->slug.'#'.$stage->task->number,
                'task_title' => $stage->task->title,
                'priority' => $stage->task->priority->value,
                'position' => $stage->position,
                'title' => $stage->title,
                'assignee' => $stage->assignee,
                'attempt' => $stage->attempt,
                'since' => $stage->status_changed_at?->toAtomString(),
            ])->values()->all(),
        ];
    }
}
