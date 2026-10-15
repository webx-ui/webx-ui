<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

use WebxUi\Tasks\Models\JournalEntry;
use WebxUi\Tasks\Models\Stage;

/**
 * Everything an agent needs to do a stage, in one answer (§5, `tasks_claim_next`).
 *
 * Every stage is a new session with a clean context, so nothing is assumed to be remembered:
 * the task and its criteria, where the project lives and its rules, the stage itself, the
 * remarks it was sent back with, the questions asked on it and their answers, and what the
 * stages before it reported.
 */
final class ContextPackage
{
    /**
     * @return array<string, mixed>
     */
    public function for(Stage $stage): array
    {
        $task = $stage->task;
        $project = $task->project;
        $stages = $task->stages()->get();

        $journal = JournalEntry::query()
            ->where('stage_id', $stage->id)
            ->whereIn('kind', [JournalKind::Rework->value, JournalKind::Question->value, JournalKind::Answer->value])
            ->orderBy('id')
            ->get();

        $rework = $journal->where('kind', JournalKind::Rework)->last();

        return [
            'project' => [
                'slug' => $project->slug,
                'title' => $project->title,
                'workdir' => $project->workdir,
                'repo_url' => $project->repo_url,
                'default_branch' => $project->default_branch,
                'rules' => $project->rules,
            ],
            'task' => [
                'id' => $task->id,
                'number' => $task->number,
                'title' => $task->title,
                'priority' => $task->priority->value,
                'branch' => $task->branch ?? $project->default_branch,
                'spec' => $task->spec,
                'acceptance' => $task->acceptance,
            ],
            'stage' => [
                'id' => $stage->id,
                'position' => $stage->position,
                'of' => $stages->count(),
                'title' => $stage->title,
                'brief' => $stage->brief,
                'acceptance' => $stage->acceptance,
                'review_mode' => $stage->review_mode->value,
                'attempt' => $stage->attempt,
                'assignee' => $stage->assignee,
                'rework_remarks' => $rework?->body,
                'questions' => $this->questions($journal->all()),
            ],
            'accepted' => $stages
                ->filter(static fn (Stage $other): bool => $other->status === StageStatus::Done && $other->position < $stage->position)
                ->map(static fn (Stage $other): array => [
                    'position' => $other->position,
                    'title' => $other->title,
                    'report' => $other->report,
                    'result_ref' => $other->result_ref,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Each question with the answer that followed it; one still unanswered has null.
     *
     * @param  list<JournalEntry>  $entries
     * @return list<array{question: string|null, asked_by: string, answer: string|null, answered_by: string|null}>
     */
    private function questions(array $entries): array
    {
        $questions = [];

        foreach ($entries as $entry) {
            if ($entry->kind === JournalKind::Question) {
                $questions[] = ['question' => $entry->body, 'asked_by' => $entry->actor_name, 'answer' => null, 'answered_by' => null];
            } elseif ($entry->kind === JournalKind::Answer && $questions !== []) {
                $last = array_key_last($questions);
                $questions[$last]['answer'] = $entry->body;
                $questions[$last]['answered_by'] = $entry->actor_name;
            }
        }

        return $questions;
    }
}
