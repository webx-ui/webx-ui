<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

use Closure;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use WebxUi\Tasks\Events\StageMoved;
use WebxUi\Tasks\Events\TaskStatusChanged;
use WebxUi\Tasks\Exceptions\TaskRefused;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;

/**
 * Everything that changes a task, its stages or its project (§3 of the spec).
 *
 * The panel and the MCP tools are two doors to these operations and decide nothing themselves:
 * every rule of §3 — which move a stage may make, what may be edited after a task is ready, who
 * may accept a stage a person has to check — is checked here, once. Each operation is one
 * transaction that re-reads what it changes under a lock, writes the journal, and brings the
 * status of the task in line with its stages.
 *
 * Handing out work is {@see Dispatcher}: it is the one operation that has to win a race.
 *
 * Values are checked with Laravel's validator, so a bad field is a `ValidationException` the
 * panel answers 422 with; a move the rules forbid is a {@see TaskRefused}.
 */
final class Tasks
{
    /** The task fields that may still change once its stages are being worked on (§3). */
    private const array LOCKED_TASK_FIELDS_EXCEPT = ['priority'];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Validation $validation,
        private readonly Journal $journal,
        private readonly Events $events,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function createProject(array $values): Project
    {
        $values = $this->validate($values, $this->projectRules(null));

        return Project::query()->create($this->projectAttributes($values));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateProject(Project $project, array $values): Project
    {
        $values = $this->validate($values, $this->projectRules($project));

        $project->fill($this->projectAttributes($values, $project))->save();

        return $project;
    }

    /**
     * A task with its stages, in one go and as a draft.
     *
     * @param  array<string, mixed>  $values
     * @param  list<array<string, mixed>>  $stages
     */
    public function create(Project $project, array $values, array $stages, Actor $actor): Task
    {
        if ($project->isArchived()) {
            throw TaskRefused::because('project-archived', ['project' => $project->slug]);
        }

        $values = $this->validate($values, $this->taskRules(required: true));
        $stages = $this->validateStages($stages);

        return $this->db->transaction(function () use ($project, $values, $stages, $actor): Task {
            // The number is the project's next one; the lock is what keeps two tasks written in
            // the same second from both being #12.
            Project::query()->whereKey($project->id)->lockForUpdate()->first();
            $number = (int) Task::withTrashed()->where('project_id', $project->id)->max('number') + 1;

            $task = Task::query()->create([
                ...$values,
                'project_id' => $project->id,
                'number' => $number,
                'status' => TaskStatus::Draft,
                'created_by' => $actor->adminId,
            ]);

            foreach (array_values($stages) as $index => $stage) {
                $this->newStage($task, $stage, $index + 1);
            }

            $this->journal->write($task, null, $actor, JournalKind::Status, null, ['from' => null, 'to' => TaskStatus::Draft->value]);

            return $task->load('stages');
        });
    }

    /**
     * Change a task and its stages within what §3 allows.
     *
     * A draft is the author's to rewrite: `$stages` is then the whole list, in order, and a stage
     * left out is deleted. Once the task is ready, the task itself keeps everything but its
     * priority; `$stages` still has to name every stage, by id and in order, but only the ones
     * nobody has started may change or move, they stay behind the ones that have, and new ones
     * go at the end. Nothing is deleted after a draft — a stage nobody wants is cancelled.
     *
     * @param  array<string, mixed>  $values
     * @param  list<array<string, mixed>>|null  $stages  null leaves the stages as they are
     */
    public function update(Task $task, array $values, ?array $stages, Actor $actor): Task
    {
        return $this->db->transaction(function () use ($task, $values, $stages, $actor): Task {
            $task = $this->lockTask($task);

            if ($task->status->isClosed()) {
                throw TaskRefused::because('task-closed', ['status' => $task->status->value]);
            }

            $draft = $task->status === TaskStatus::Draft;

            if (! $draft) {
                $locked = array_values(array_diff(array_keys($values), self::LOCKED_TASK_FIELDS_EXCEPT));

                if ($locked !== []) {
                    throw TaskRefused::because('task-started', ['fields' => implode(', ', $locked)]);
                }
            }

            $values = $this->validate($values, $this->taskRules(required: false));
            $task->fill($values);
            $fields = array_keys($task->getDirty());
            $task->save();

            $changed = [];

            if ($stages !== null) {
                $stages = $this->validateStages($stages, withIds: true);
                $changed = $draft ? $this->replaceStages($task, $stages) : $this->editStartedStages($task, $stages);
            }

            if ($fields === [] && $changed === []) {
                return $task->load('stages');
            }

            $this->journal->write($task, null, $actor, JournalKind::Edit, null, array_filter([
                'fields' => $fields,
                'stages' => $changed,
            ]));

            if (! $draft) {
                $this->advance($task);
                $this->sync($task, $actor);
            }

            return $task->load('stages');
        });
    }

    /** `draft` → `ready`: the first stage can be taken. */
    public function ready(Task $task, Actor $actor): Task
    {
        return $this->db->transaction(function () use ($task, $actor): Task {
            $task = $this->lockTask($task);

            if ($task->status !== TaskStatus::Draft) {
                throw TaskRefused::because('task-not-draft', ['status' => $task->status->value]);
            }

            if (! $task->stages()->exists()) {
                throw TaskRefused::because('task-no-stages');
            }

            if ($task->project->isArchived()) {
                throw TaskRefused::because('project-archived', ['project' => $task->project->slug]);
            }

            $this->setTaskStatus($task, TaskStatus::Ready, $actor);
            $this->advance($task);
            $this->sync($task, $actor);

            return $task->load('stages');
        });
    }

    /** The agent that holds the stage says what it did: → `review`. */
    public function report(Stage $stage, string $report, ?string $resultRef, Actor $actor): Stage
    {
        return $this->onStage($stage, function (Stage $stage, Task $task) use ($report, $resultRef, $actor): void {
            $this->requireStatus($stage, StageStatus::InProgress);
            $this->requireHolder($stage, $actor);
            $report = $this->requireText($report, 'report');

            $stage->report = $report;
            $stage->result_ref = $resultRef !== null && trim($resultRef) !== '' ? trim($resultRef) : null;
            $stage->reported_at = Carbon::now();

            $this->move($stage, StageStatus::Review, $actor, JournalKind::Report, $report, ['result_ref' => $stage->result_ref]);
            $this->sync($task, $actor);
        });
    }

    /** A reviewer takes the stage: → `done`, and the next one may start. */
    public function accept(Stage $stage, Actor $actor, ?string $note = null): Stage
    {
        return $this->onStage($stage, function (Stage $stage, Task $task) use ($actor, $note): void {
            $this->requireStatus($stage, StageStatus::Review);
            $this->requireReviewer($stage, $actor);

            $stage->accepted_at = Carbon::now();

            $this->move($stage, StageStatus::Done, $actor, JournalKind::Status, $this->optionalText($note));
            $this->advance($task);
            $this->sync($task, $actor);
        });
    }

    /** A reviewer sends the stage back with remarks: → `rework`, and the attempt counts up. */
    public function rework(Stage $stage, string $remarks, Actor $actor): Stage
    {
        return $this->onStage($stage, function (Stage $stage, Task $task) use ($remarks, $actor): void {
            $this->requireStatus($stage, StageStatus::Review);
            $this->requireReviewer($stage, $actor);
            $remarks = $this->requireText($remarks, 'remarks');

            $stage->attempt++;

            $this->move($stage, StageStatus::Rework, $actor, JournalKind::Rework, $remarks);
            $this->sync($task, $actor);
        });
    }

    /**
     * Take a stage back from an agent that fell over or hung: it goes where it was taken from —
     * `rework` when it had been sent back before, `ready` otherwise — so it keeps its place in
     * the queue.
     */
    public function release(Stage $stage, Actor $actor, ?string $reason = null): Stage
    {
        return $this->onStage($stage, function (Stage $stage, Task $task) use ($actor, $reason): void {
            $this->requireStatus($stage, StageStatus::InProgress);

            $stage->assignee = null;
            $stage->claimed_at = null;

            $to = $stage->attempt > 1 ? StageStatus::Rework : StageStatus::Ready;

            $this->move($stage, $to, $actor, JournalKind::Status, $this->optionalText($reason));
            $this->sync($task, $actor);
        });
    }

    /**
     * The agent cannot go on without a person: → `blocked` with the question. The stage is not
     * handed out until a person answers.
     */
    public function block(Stage $stage, string $question, Actor $actor): Stage
    {
        return $this->onStage($stage, function (Stage $stage, Task $task) use ($question, $actor): void {
            $this->requireStatus($stage, StageStatus::InProgress);
            $this->requireHolder($stage, $actor);
            $question = $this->requireText($question, 'question');

            $this->move($stage, StageStatus::Blocked, $actor, JournalKind::Question, $question);
            $this->sync($task, $actor);
        });
    }

    /**
     * A person answers the question: → `ready`, and the answer goes to whoever takes it next.
     * Never an agent — an answer from another program is not the decision the question asked for.
     */
    public function unblock(Stage $stage, string $answer, Actor $actor): Stage
    {
        return $this->onStage($stage, function (Stage $stage, Task $task) use ($answer, $actor): void {
            $this->requireStatus($stage, StageStatus::Blocked);

            if (! $actor->isPerson()) {
                throw TaskRefused::because('person-only');
            }

            $answer = $this->requireText($answer, 'answer');

            $stage->assignee = null;
            $stage->claimed_at = null;

            $this->move($stage, StageStatus::Ready, $actor, JournalKind::Answer, $answer);
            $this->sync($task, $actor);
        });
    }

    /** A remark in the journal of the task, or of one of its stages. */
    public function comment(Task $task, ?Stage $stage, string $body, Actor $actor): void
    {
        if ($stage !== null && $stage->task_id !== $task->id) {
            throw TaskRefused::because('stage-elsewhere');
        }

        $this->journal->write($task, $stage, $actor, JournalKind::Comment, $this->requireText($body, 'comment'));
    }

    /** Cancel the task; every stage not yet done is cancelled with it. */
    public function cancel(Task $task, Actor $actor, ?string $reason = null): Task
    {
        return $this->db->transaction(function () use ($task, $actor, $reason): Task {
            $task = $this->lockTask($task);

            if ($task->status->isClosed()) {
                throw TaskRefused::because('task-closed', ['status' => $task->status->value]);
            }

            foreach ($task->stages as $stage) {
                if (! $stage->status->isClosed()) {
                    $this->move($stage, StageStatus::Cancelled, $actor);
                }
            }

            $this->setTaskStatus($task, TaskStatus::Cancelled, $actor, $this->optionalText($reason));

            return $task->load('stages');
        });
    }

    /**
     * Cancel one stage and go on without it. The last stage standing is not cancelled this way:
     * a task with nothing left to do is a cancelled task, and saying so is the other operation.
     */
    public function cancelStage(Stage $stage, Actor $actor, ?string $reason = null): Stage
    {
        return $this->onStage($stage, function (Stage $stage, Task $task) use ($actor, $reason): void {
            if ($stage->status->isClosed()) {
                throw TaskRefused::because('stage-transition', ['from' => $stage->status->value, 'to' => StageStatus::Cancelled->value]);
            }

            $others = $task->stages->where('id', '!=', $stage->id);

            if ($others->every(static fn (Stage $other): bool => $other->status === StageStatus::Cancelled)) {
                throw TaskRefused::because('last-stage');
            }

            $stage->assignee = null;

            $this->move($stage, StageStatus::Cancelled, $actor, JournalKind::Status, $this->optionalText($reason));

            if ($task->status !== TaskStatus::Draft) {
                $this->advance($task);
                $this->sync($task, $actor);
            }
        });
    }

    /**
     * Move a stage and write it down. The one way a stage's status changes, apart from the
     * conditional update the dispatcher wins a race with.
     *
     * @param  array<string, mixed>  $meta
     */
    private function move(Stage $stage, StageStatus $to, Actor $actor, JournalKind $kind = JournalKind::Status, ?string $body = null, array $meta = []): void
    {
        $from = $stage->status;

        if (! $from->canBecome($to)) {
            throw TaskRefused::because('stage-transition', ['from' => $from->value, 'to' => $to->value]);
        }

        $stage->status = $to;
        $stage->status_changed_at = Carbon::now();
        $stage->save();

        $this->journal->write($stage->task, $stage, $actor, $kind, $body, [
            'from' => $from->value,
            'to' => $to->value,
            'attempt' => $stage->attempt,
            ...$meta,
        ]);

        $this->events->dispatch(new StageMoved($stage->id, $from, $to, $body));
    }

    /**
     * Bring the task's status in line with its stages (§3). A draft and a cancelled task keep
     * theirs: those two are the only ones ever set by hand.
     */
    public function sync(Task $task, Actor $actor): void
    {
        if ($task->status === TaskStatus::Draft || $task->status === TaskStatus::Cancelled) {
            return;
        }

        $statuses = $task->stages()->get()->map(static fn (Stage $stage): StageStatus => $stage->status);
        $status = TaskStatus::of($statuses);

        if ($status !== $task->status) {
            $this->setTaskStatus($task, $status, $actor);
        }
    }

    /**
     * Open the stage whose turn it is (§2: stages go in order).
     *
     * The first stage that is neither done nor cancelled is the frontier: if it is waiting, it
     * becomes ready. Any ready stage behind the frontier goes back to waiting — that only happens
     * after a reorder put another stage in front of it.
     */
    private function advance(Task $task): void
    {
        $frontier = false;

        foreach ($task->stages()->get() as $stage) {
            if ($stage->status->isClosed()) {
                continue;
            }

            if (! $frontier) {
                $frontier = true;

                if ($stage->status === StageStatus::Pending) {
                    $this->move($stage, StageStatus::Ready, Actor::system());
                }

                continue;
            }

            if ($stage->status === StageStatus::Ready) {
                $this->move($stage, StageStatus::Pending, Actor::system());
            }
        }
    }

    private function setTaskStatus(Task $task, TaskStatus $to, Actor $actor, ?string $body = null): void
    {
        $from = $task->status;

        $task->status = $to;
        $task->closed_at = $to->isClosed() ? Carbon::now() : null;
        $task->save();

        $this->journal->write($task, null, $actor, JournalKind::Status, $body, ['from' => $from->value, 'to' => $to->value]);

        $this->events->dispatch(new TaskStatusChanged($task->id, $from, $to));
    }

    /**
     * Run an operation on a stage under a lock on it and its task, and answer the stage as it is
     * afterwards.
     *
     * @param  Closure(Stage, Task): void  $work
     */
    private function onStage(Stage $stage, Closure $work): Stage
    {
        return $this->db->transaction(function () use ($stage, $work): Stage {
            $task = $this->lockTask($stage->task);

            if ($task->trashed()) {
                throw TaskRefused::because('task-deleted');
            }

            /** @var Stage $stage */
            $stage = Stage::query()->whereKey($stage->id)->lockForUpdate()->firstOrFail();
            $stage->setRelation('task', $task);

            $work($stage, $task);

            return $stage->refresh();
        });
    }

    private function lockTask(Task $task): Task
    {
        /** @var Task */
        return Task::withTrashed()->whereKey($task->id)->lockForUpdate()->firstOrFail();
    }

    private function requireStatus(Stage $stage, StageStatus $status): void
    {
        if ($stage->status !== $status) {
            throw TaskRefused::because('stage-not', ['status' => $status->value, 'actual' => $stage->status->value]);
        }
    }

    /** An agent works only on the stage it took; a person may stand in for one. */
    private function requireHolder(Stage $stage, Actor $actor): void
    {
        if ($actor->isAgent() && $stage->assignee !== $actor->name) {
            throw TaskRefused::because('not-holder', ['assignee' => (string) $stage->assignee]);
        }
    }

    /**
     * Who may decide on a reported stage: never the agent that did it, and on a stage marked
     * `human` nobody but a person (§5, permissions).
     */
    private function requireReviewer(Stage $stage, Actor $actor): void
    {
        if ($stage->review_mode === ReviewMode::Human && ! $actor->isPerson()) {
            throw TaskRefused::because('human-review');
        }

        if ($actor->isAgent() && $stage->assignee === $actor->name) {
            throw TaskRefused::because('own-work');
        }
    }

    private function requireText(string $text, string $what): string
    {
        $text = trim($text);

        if ($text === '') {
            throw TaskRefused::because('text-required', ['what' => $what]);
        }

        return $text;
    }

    private function optionalText(?string $text): ?string
    {
        return $text !== null && trim($text) !== '' ? trim($text) : null;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function newStage(Task $task, array $stage, int $position): Stage
    {
        return Stage::query()->create([
            'task_id' => $task->id,
            'position' => $position,
            'title' => $stage['title'],
            'brief' => $stage['brief'] ?? null,
            'acceptance' => $stage['acceptance'] ?? null,
            'review_mode' => $stage['review_mode'] ?? ReviewMode::Auto->value,
            'status' => StageStatus::Pending,
            'status_changed_at' => Carbon::now(),
        ]);
    }

    /**
     * A draft's stages, rewritten to the list.
     *
     * @param  list<array<string, mixed>>  $stages
     * @return list<string> what changed, for the journal
     */
    private function replaceStages(Task $task, array $stages): array
    {
        $existing = $task->stages()->get()->keyBy('id');
        $this->refuseForeignIds($existing->keys()->all(), $stages);

        $kept = [];
        $changed = [];

        foreach ($stages as $index => $values) {
            $id = isset($values['id']) ? (int) $values['id'] : null;

            if ($id === null) {
                $stage = $this->newStage($task, $values, $index + 1);
                $changed[] = "added #{$stage->position}";
                $kept[] = $stage->id;

                continue;
            }

            /** @var Stage $stage */
            $stage = $existing[$id];
            $stage->fill(['position' => $index + 1, ...$this->stageFields($values)]);

            if ($stage->isDirty()) {
                $changed[] = "changed #{$stage->position}";
                $stage->save();
            }

            $kept[] = $id;
        }

        foreach ($existing as $id => $stage) {
            if (! in_array($id, $kept, true)) {
                $changed[] = "removed «{$stage->title}»";
                $stage->delete();
            }
        }

        return $changed;
    }

    /**
     * The stages of a task that has started, edited within §3.
     *
     * @param  list<array<string, mixed>>  $stages
     * @return list<string> what changed, for the journal
     */
    private function editStartedStages(Task $task, array $stages): array
    {
        $existing = $task->stages()->get();
        $byId = $existing->keyBy('id');
        $this->refuseForeignIds($byId->keys()->all(), $stages);

        $named = array_values(array_filter(array_map(
            static fn (array $values): ?int => isset($values['id']) ? (int) $values['id'] : null,
            $stages,
        )));

        $missing = $existing->whereNotIn('id', $named);

        if ($missing->isNotEmpty()) {
            throw TaskRefused::because('stage-missing', ['stages' => $missing->map(static fn (Stage $stage): string => '#'.$stage->position)->implode(', ')]);
        }

        // A stage that has started — or was cancelled — stands where it stood. The ones nobody
        // has started fill the other places in any order; they are all behind the stage being
        // worked on, because only the first open stage is ever opened.
        $fixed = $existing
            ->reject(static fn (Stage $stage): bool => $stage->status->isEditable())
            ->mapWithKeys(static fn (Stage $stage): array => [$stage->id => $stage->position])
            ->all();
        $seenNew = false;

        foreach ($stages as $index => $values) {
            $id = isset($values['id']) ? (int) $values['id'] : null;

            if ($id === null) {
                $seenNew = true;

                continue;
            }

            if ($seenNew) {
                throw TaskRefused::because('stage-new-at-end');
            }

            if (array_key_exists($id, $fixed) && $fixed[$id] !== $index + 1) {
                throw TaskRefused::because('stage-started-order');
            }
        }

        $changed = [];

        foreach ($stages as $index => $values) {
            $id = isset($values['id']) ? (int) $values['id'] : null;

            if ($id === null) {
                $stage = $this->newStage($task, $values, $index + 1);
                $changed[] = "added #{$stage->position}";

                continue;
            }

            /** @var Stage $stage */
            $stage = $byId[$id];
            $fields = $this->stageFields($values);

            if (! $stage->status->isEditable()) {
                $stage->fill($fields);

                if ($stage->isDirty()) {
                    throw TaskRefused::because('stage-started', ['stage' => '#'.$stage->getOriginal('position'), 'status' => $stage->status->value]);
                }

                continue;
            }

            $stage->fill(['position' => $index + 1, ...$fields]);

            if ($stage->isDirty()) {
                $changed[] = "changed #{$stage->position}";
                $stage->save();
            }
        }

        return $changed;
    }

    /**
     * @param  list<int|string>  $ids
     * @param  list<array<string, mixed>>  $stages
     */
    private function refuseForeignIds(array $ids, array $stages): void
    {
        $seen = [];

        foreach ($stages as $values) {
            if (! isset($values['id'])) {
                continue;
            }

            $id = (int) $values['id'];

            if (! in_array($id, $ids, true) || in_array($id, $seen, true)) {
                throw TaskRefused::because('stage-elsewhere');
            }

            $seen[] = $id;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function stageFields(array $values): array
    {
        return array_intersect_key($values, array_flip(['title', 'brief', 'acceptance', 'review_mode']));
    }

    /**
     * @param  list<array<string, mixed>>  $stages
     * @return list<array<string, mixed>>
     */
    private function validateStages(array $stages, bool $withIds = false): array
    {
        $rules = [
            'stages' => ['array'],
            'stages.*' => ['array'],
            'stages.*.title' => [$withIds ? 'required_without:stages.*.id' : 'required', 'string', 'max:255'],
            'stages.*.brief' => ['nullable', 'string'],
            'stages.*.acceptance' => ['nullable', 'string'],
            'stages.*.review_mode' => ['nullable', Rule::enum(ReviewMode::class)],
        ];

        if ($withIds) {
            $rules['stages.*.id'] = ['nullable', 'integer'];
        }

        /** @var array<int, array<string, mixed>> $valid */
        $valid = $this->validate(['stages' => array_values($stages)], $rules)['stages'] ?? [];

        // The validator hands the items back keyed by their index but not in its order, and the
        // order is the whole point of the list.
        ksort($valid);

        return array_values($valid);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function taskRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'spec' => ['sometimes', 'nullable', 'string'],
            'acceptance' => ['sometimes', 'nullable', 'string'],
            'branch' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function projectRules(?Project $project): array
    {
        $presence = $project === null ? 'required' : 'sometimes';

        return [
            'slug' => [$presence, 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::unique('tasks_projects', 'slug')->ignore($project?->id)],
            'title' => [$presence, 'string', 'max:255'],
            'workdir' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'repo_url' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'default_branch' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rules' => ['sometimes', 'nullable', 'string'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function projectAttributes(array $values, ?Project $project = null): array
    {
        if (array_key_exists('archived', $values)) {
            $archived = (bool) $values['archived'];
            unset($values['archived']);

            // Archiving twice keeps the first date: it is when the project stopped.
            $values['archived_at'] = $archived ? ($project->archived_at ?? Carbon::now()) : null;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $values, array $rules): array
    {
        /** @var array<string, mixed> */
        return $this->validation->make($values, $rules)->validate();
    }
}
