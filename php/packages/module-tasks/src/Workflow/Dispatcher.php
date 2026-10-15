<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use WebxUi\Tasks\Events\StageMoved;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Models\Stage;

/**
 * The one place work is handed out (§5, `tasks_claim_next`).
 *
 * Two agents asking in the same second must not both get the same stage. The candidates are read
 * without a lock, in queue order — rework before ready, then the task's priority, then its age —
 * and each is taken with a conditional update that only succeeds while the stage is still in the
 * status it was read in. The database decides who won by the number of rows changed; the loser
 * goes on to the next candidate, and an agent that finds nothing left gets null, not an error.
 */
final class Dispatcher
{
    /** How many candidates one call tries before it says there is nothing: a race lost this many times in a row is a queue that has just been emptied. */
    private const int CANDIDATES = 20;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Tasks $tasks,
        private readonly Journal $journal,
        private readonly ContextPackage $context,
        private readonly Events $events,
    ) {}

    /**
     * Take the next stage for this agent and answer everything it needs to do it — or null when
     * there is nothing to take.
     *
     * @return array<string, mixed>|null
     */
    public function claimNext(Actor $agent, ?Project $project = null): ?array
    {
        foreach ($this->candidates($project) as $candidate) {
            if ($this->take($candidate, $agent)) {
                return $this->context->for(Stage::query()->findOrFail($candidate->id));
            }
        }

        return null;
    }

    /**
     * The stages that may be taken, in the order they should be.
     *
     * @return Collection<int, Stage>
     */
    public function candidates(?Project $project = null): Collection
    {
        return Stage::query()
            ->select('tasks_stages.*')
            ->join('tasks_tasks', 'tasks_tasks.id', '=', 'tasks_stages.task_id')
            ->join('tasks_projects', 'tasks_projects.id', '=', 'tasks_tasks.project_id')
            ->whereIn('tasks_stages.status', [StageStatus::Rework->value, StageStatus::Ready->value])
            ->whereNotIn('tasks_tasks.status', [TaskStatus::Draft->value, TaskStatus::Cancelled->value, TaskStatus::Done->value])
            ->whereNull('tasks_tasks.deleted_at')
            ->whereNull('tasks_projects.archived_at')
            ->when($project !== null, static fn ($query) => $query->where('tasks_tasks.project_id', $project?->id))
            ->orderByRaw('case tasks_stages.status when ? then 0 else 1 end', [StageStatus::Rework->value])
            ->orderByRaw(
                'case tasks_tasks.priority when ? then 0 when ? then 1 else 2 end',
                [Priority::High->value, Priority::Normal->value],
            )
            ->orderBy('tasks_tasks.created_at')
            ->orderBy('tasks_tasks.id')
            ->orderBy('tasks_stages.position')
            ->limit(self::CANDIDATES)
            ->get();
    }

    /**
     * Take one stage as it was read, or lose it to whoever took it first.
     *
     * The update names the status the stage was read in, so a stage that was sent back to rework
     * in between — and so moved up the queue — is not taken as the ready one this call saw.
     */
    public function take(Stage $candidate, Actor $agent): bool
    {
        return $this->db->transaction(function () use ($candidate, $agent): bool {
            $now = Carbon::now();

            $taken = Stage::query()
                ->whereKey($candidate->id)
                ->where('status', $candidate->status->value)
                ->update([
                    'status' => StageStatus::InProgress->value,
                    'assignee' => $agent->name,
                    'claimed_at' => $now,
                    // A new stint: the stage may be reported as hung again once this one runs long.
                    'stale_notified_at' => null,
                    'status_changed_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($taken !== 1) {
                return false;
            }

            $stage = Stage::query()->findOrFail($candidate->id);

            $this->journal->write($stage->task, $stage, $agent, JournalKind::Status, null, [
                'from' => $candidate->status->value,
                'to' => StageStatus::InProgress->value,
                'attempt' => $stage->attempt,
            ]);

            $this->events->dispatch(new StageMoved($stage->id, $candidate->status, StageStatus::InProgress));
            $this->tasks->sync($stage->task, $agent);

            return true;
        });
    }
}
