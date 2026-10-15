<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

use Illuminate\Support\Carbon;
use WebxUi\Tasks\Models\JournalEntry;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;

/**
 * Projects, tasks, stages and journal lines as plain arrays — one shape for every door, so an agent
 * and the panel read the same names for the same things.
 */
final class Present
{
    /**
     * @return array<string, mixed>
     */
    public static function project(Project $project): array
    {
        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'title' => $project->title,
            'workdir' => $project->workdir,
            'repo_url' => $project->repo_url,
            'default_branch' => $project->default_branch,
            'rules' => $project->rules,
            'archived' => $project->isArchived(),
        ];
    }

    /**
     * A task as a row of a list: where it is and which stage it is on, without the long texts.
     *
     * @return array<string, mixed>
     */
    public static function row(Task $task): array
    {
        $stages = $task->stages;
        $current = $stages->first(static fn (Stage $stage): bool => ! $stage->status->isClosed());

        return [
            'id' => $task->id,
            'ref' => $task->project->slug.'#'.$task->number,
            'project' => $task->project->slug,
            'number' => $task->number,
            'title' => $task->title,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'branch' => $task->branch,
            'stages' => $stages->count(),
            'done' => $stages->filter(static fn (Stage $stage): bool => $stage->status === StageStatus::Done)->count(),
            'current_stage' => $current === null ? null : [
                'id' => $current->id,
                'position' => $current->position,
                'title' => $current->title,
                'status' => $current->status->value,
                'review_mode' => $current->review_mode->value,
                'assignee' => $current->assignee,
                'attempt' => $current->attempt,
            ],
            'updated_at' => self::date($task->updated_at),
        ];
    }

    /**
     * A task in full: its texts, every stage with its report, and the journal.
     *
     * @return array<string, mixed>
     */
    public static function task(Task $task): array
    {
        return [
            ...self::row($task),
            'spec' => $task->spec,
            'acceptance' => $task->acceptance,
            'created_at' => self::date($task->created_at),
            'closed_at' => self::date($task->closed_at),
            'stages' => $task->stages->map(self::stage(...))->values()->all(),
            'journal' => $task->journal->map(self::entry(...))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function stage(Stage $stage): array
    {
        return [
            'id' => $stage->id,
            'position' => $stage->position,
            'title' => $stage->title,
            'brief' => $stage->brief,
            'acceptance' => $stage->acceptance,
            'review_mode' => $stage->review_mode->value,
            'status' => $stage->status->value,
            'assignee' => $stage->assignee,
            'attempt' => $stage->attempt,
            'report' => $stage->report,
            'result_ref' => $stage->result_ref,
            'claimed_at' => self::date($stage->claimed_at),
            'reported_at' => self::date($stage->reported_at),
            'accepted_at' => self::date($stage->accepted_at),
            'status_changed_at' => self::date($stage->status_changed_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function entry(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'stage_id' => $entry->stage_id,
            'actor_type' => $entry->actor_type->value,
            'actor_name' => $entry->actor_name,
            'kind' => $entry->kind->value,
            'body' => $entry->body,
            'meta' => $entry->meta,
            'created_at' => self::date($entry->created_at),
        ];
    }

    private static function date(?Carbon $date): ?string
    {
        return $date?->toAtomString();
    }
}
