<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Mcp;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Keys\Keys;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Scopes;
use WebxUi\Mcp\Tool;
use WebxUi\Tasks\Exceptions\TaskRefused;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\Workflow\Actor;
use WebxUi\Tasks\Workflow\Dispatcher;
use WebxUi\Tasks\Workflow\Present;
use WebxUi\Tasks\Workflow\Priority;
use WebxUi\Tasks\Workflow\Queue;
use WebxUi\Tasks\Workflow\ReviewMode;
use WebxUi\Tasks\Workflow\StageStatus;
use WebxUi\Tasks\Workflow\Tasks;
use WebxUi\Tasks\Workflow\TaskStatus;

/**
 * The queue of work by the agent's door (§5 of the spec).
 *
 * Thin on purpose: every rule lives in {@see Tasks} and {@see Dispatcher}, and a tool only finds
 * what it was pointed at, decides who is acting, and turns a refusal into words the agent reads.
 *
 * Who is acting is the `agent` argument when it is given — the name of the session, which the
 * journal and the stage keep. Without it the caller is the administrator behind the connection,
 * and only a person can be that: a key issued for a program — whatever scopes it carries, all of
 * them included — must say which agent it is, and is never taken for a person. That is what keeps a stage marked `human`
 * out of every agent's reach however its key was made.
 *
 * The four scopes of §5 are the four kinds of work: `tasks:read` looks, `tasks:write` plans,
 * `tasks:work` does stages, `tasks:review` decides on them. A comment is the one tool two of them
 * reach.
 */
final class TasksTools
{
    private const string READ = 'tasks:read';

    private const string WRITE = 'tasks:write';

    private const string WORK = 'tasks:work';

    private const string REVIEW = 'tasks:review';

    public function __construct(
        private readonly Tasks $tasks,
        private readonly Dispatcher $dispatcher,
        private readonly Queue $queue,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $project = ['type' => ['string', 'integer'], 'description' => 'A project: its slug, or its id.'];
        $task = ['type' => ['integer', 'string'], 'description' => 'A task: its id, or its reference "<project>#<number>" — "shop#12".'];
        $stage = ['type' => 'integer', 'description' => 'A stage, by the id tasks_get, tasks_queue and tasks_claim_next give.'];
        $agent = ['type' => 'string', 'description' => 'Your session name, e.g. "worker-7". Required for an agent: it is what the stage and the journal record. Leave it out only when you act for the person who connected you.'];
        $stageItem = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'An existing stage; leave out for a new one.'],
                'title' => ['type' => 'string'],
                'brief' => ['type' => 'string', 'description' => 'The assignment, markdown. Everything a fresh session needs that the task does not say.'],
                'acceptance' => ['type' => 'string', 'description' => 'How the stage is checked, markdown.'],
                'review_mode' => ['type' => 'string', 'enum' => ['auto', 'human'], 'description' => 'auto — an agent reviews; human — only a person accepts. auto when omitted.'],
            ],
        ];
        $taskValues = [
            'title' => ['type' => 'string'],
            'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high']],
            'spec' => ['type' => 'string', 'description' => 'The specification, markdown.'],
            'acceptance' => ['type' => 'string', 'description' => 'Acceptance criteria of the whole task, markdown.'],
            'branch' => ['type' => 'string', 'description' => 'The branch the work goes into; the project\'s default branch when omitted.'],
        ];
        $projectValues = [
            'slug' => ['type' => 'string', 'description' => 'Lowercase letters, digits and dashes; never changes the reference of its tasks.'],
            'title' => ['type' => 'string'],
            'workdir' => ['type' => 'string', 'description' => 'The folder an agent is started in.'],
            'repo_url' => ['type' => 'string'],
            'default_branch' => ['type' => 'string'],
            'rules' => ['type' => 'string', 'description' => 'The project\'s rules for agents, free text; handed out with every stage.'],
            'archived' => ['type' => 'boolean', 'description' => 'An archived project takes no new tasks and hands out nothing.'],
        ];

        return Arguments::strictAll([
            Tool::read(
                'projects_list',
                'The projects — where work happens: the folder and repository an agent works in, and the rules it '
                .'gets with every stage.',
                fn (array $arguments): array => [
                    'projects' => Project::query()
                        ->when(($arguments['archived'] ?? false) !== true, static fn (Builder $query) => $query->whereNull('archived_at'))
                        ->orderBy('slug')
                        ->get()
                        ->map(Present::project(...))
                        ->all(),
                ],
                ['properties' => ['archived' => ['type' => 'boolean', 'description' => 'Archived projects too.']]],
                scope: self::READ,
                permission: $this->readers(),
            ),

            Tool::mutating(
                'projects_create',
                'Add a project. Its slug is how tasks are referred to: "shop#12".',
                fn (array $arguments): array => $this->write($arguments, fn (): array => [
                    'project' => Present::project($this->tasks->createProject($this->values($arguments, $projectValues))),
                ]),
                ['properties' => $projectValues, 'required' => ['slug', 'title']],
                scope: self::WRITE,
                permission: 'tasks.manage',
            ),

            Tool::mutating(
                'projects_update',
                'Change a project. Fields left out keep their values.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => [
                    'project' => Present::project($this->tasks->updateProject(
                        $this->project($arguments['project'] ?? null),
                        $this->values($arguments, $projectValues),
                    )),
                ]),
                ['properties' => ['project' => $project, ...$projectValues], 'required' => ['project']],
                scope: self::WRITE,
                permission: 'tasks.manage',
            ),

            Tool::read(
                'list',
                'Tasks, highest priority and most recently changed first, each with the stage it is on. '
                .'review_mode "human" narrows to tasks with a stage waiting for a person\'s review; waiting_for_person '
                .'adds the ones whose agent asked a question.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'project' => $project,
                    'status' => ['type' => 'string', 'enum' => array_column(TaskStatus::cases(), 'value')],
                    'review_mode' => ['type' => 'string', 'enum' => ['auto', 'human'], 'description' => 'Only tasks with a reported stage of this review mode.'],
                    'waiting_for_person' => ['type' => 'boolean', 'description' => 'Only tasks with a stage a person has to act on: a human review, or a question.'],
                    'limit' => ['type' => 'integer', 'description' => 'At most this many; 50 when omitted, 200 at most.'],
                ]],
                scope: self::READ,
                permission: $this->readers(),
            ),

            Tool::read(
                'get',
                'One task in full: the specification and criteria, every stage with its assignment, criteria, '
                .'status, report and result, and the journal of everything that happened.',
                fn (array $arguments): array => ['task' => Present::task($this->task($arguments['task'] ?? null)->load('stages', 'journal', 'project'))],
                ['properties' => ['task' => $task], 'required' => ['task']],
                scope: self::READ,
                permission: $this->readers(),
            ),

            Tool::read(
                'queue',
                'The dispatcher\'s view, cheap enough to call every minute: every stage waiting for somebody — '
                .'rework, ready, in progress, in review (with its review mode), blocked — with its project, task, '
                .'agent, attempt and when it last changed status, oldest first.',
                fn (array $arguments): array => $this->queue->summary(isset($arguments['project']) ? $this->project($arguments['project']) : null),
                ['properties' => ['project' => $project]],
                scope: self::READ,
                permission: $this->readers(),
            ),

            Tool::mutating(
                'create',
                'Write a task with its stages in one call. It is a draft: nothing is handed out until tasks_ready. '
                .'Stages run in the order given, each one a separate agent session with a clean context — so each '
                .'brief has to stand on its own.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->write($arguments, fn (): array => [
                    'task' => $this->full($this->tasks->create(
                        $this->project($arguments['project'] ?? null),
                        $this->values($arguments, $taskValues),
                        $this->stages($arguments['stages'] ?? []),
                        $this->actor($arguments, $user),
                    )),
                ]),
                ['properties' => [
                    'project' => $project,
                    ...$taskValues,
                    'stages' => ['type' => 'array', 'items' => $stageItem, 'description' => 'The stages, first to last.'],
                    'agent' => $agent,
                ], 'required' => ['project', 'title', 'stages']],
                scope: self::WRITE,
                permission: 'tasks.manage',
            ),

            Tool::mutating(
                'update',
                'Change a task. A draft changes freely, and stages, when given, are the whole list in order — a '
                .'stage left out is deleted. Once the task is ready only its priority changes; stages, when given, '
                .'must still name every stage by id in order, but only the ones nobody has started may change or '
                .'move, started and cancelled ones stay where they are, and new ones go at the end.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->write($arguments, fn (): array => [
                    'task' => $this->full($this->tasks->update(
                        $this->task($arguments['task'] ?? null),
                        $this->values($arguments, $taskValues),
                        array_key_exists('stages', $arguments) ? $this->stages($arguments['stages']) : null,
                        $this->actor($arguments, $user),
                    )),
                ]),
                ['properties' => [
                    'task' => $task,
                    ...$taskValues,
                    'stages' => ['type' => 'array', 'items' => $stageItem, 'description' => 'Every stage, first to last.'],
                    'agent' => $agent,
                ], 'required' => ['task']],
                scope: self::WRITE,
                permission: 'tasks.manage',
            ),

            Tool::mutating(
                'ready',
                'Put a draft to work: its first stage can be taken. Only a person, or an assistant they asked, '
                .'should do this — it is the moment agents start spending effort.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->write($arguments, fn (): array => [
                    'task' => $this->full($this->tasks->ready($this->task($arguments['task'] ?? null), $this->actor($arguments, $user))),
                ]),
                ['properties' => ['task' => $task, 'agent' => $agent], 'required' => ['task']],
                scope: self::WRITE,
                permission: 'tasks.manage',
            ),

            Tool::mutating(
                'claim_next',
                'Take the next stage to work on — the only way work is handed out, and safe to call from several '
                .'sessions at once. Stages sent back come first, then by the task\'s priority and age. The answer is '
                .'everything you need: the task and its criteria, the project\'s folder, branch and rules, the stage '
                .'with its attempt, the remarks it was sent back with, the questions asked on it and their answers, '
                .'and the reports of the stages before it. Nothing to take is an empty answer, not an error. Read '
                .'tasks://rules before your first stage.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->write($arguments, fn (): array => [
                    'claimed' => $this->dispatcher->claimNext(
                        $this->agent($arguments, $user),
                        isset($arguments['project']) ? $this->project($arguments['project']) : null,
                    ),
                ]),
                ['properties' => [
                    'agent' => ['type' => 'string', 'description' => 'Your session name, e.g. "worker-7".'],
                    'project' => $project + ['description' => 'Only stages of this project: its slug or id.'],
                ], 'required' => ['agent']],
                scope: self::WORK,
                permission: 'tasks.work',
            ),

            Tool::mutating(
                'stage_report',
                'Report the stage you hold: what was done, how to check it, the result (branch, commit, PR), what '
                .'was not done and why, risks outside the stage. It goes to review; you do not review it yourself.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->onStage($arguments, fn (Stage $stage): Stage => $this->tasks->report(
                    $stage,
                    $this->text($arguments, 'report'),
                    isset($arguments['result_ref']) ? (string) $arguments['result_ref'] : null,
                    $this->actor($arguments, $user),
                )),
                ['properties' => [
                    'stage' => $stage,
                    'report' => ['type' => 'string', 'description' => 'Markdown: ## Done, ## How to check, ## Result, ## Not done and why, ## Risks and what was touched outside the stage.'],
                    'result_ref' => ['type' => 'string', 'description' => 'The branch, commit or PR address.'],
                    'agent' => $agent,
                ], 'required' => ['stage', 'report']],
                scope: self::WORK,
                permission: 'tasks.work',
            ),

            Tool::mutating(
                'stage_accept',
                'Accept a reported stage: it is done, and the next stage can be taken; the last one closes the task. '
                .'A stage marked human is accepted by a person only, and an agent never accepts its own work.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->onStage($arguments, fn (Stage $stage): Stage => $this->tasks->accept(
                    $stage,
                    $this->actor($arguments, $user),
                    isset($arguments['note']) ? (string) $arguments['note'] : null,
                )),
                ['properties' => [
                    'stage' => $stage,
                    'note' => ['type' => 'string', 'description' => 'Why it is accepted, for the journal. Optional.'],
                    'agent' => $agent,
                ], 'required' => ['stage']],
                scope: self::REVIEW,
                permission: 'tasks.review',
            ),

            Tool::mutating(
                'stage_rework',
                'Send a reported stage back with remarks. The next session that takes it gets the remarks; the '
                .'attempt counts up. Say exactly what is wrong against the stage\'s criteria.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->onStage($arguments, fn (Stage $stage): Stage => $this->tasks->rework(
                    $stage,
                    $this->text($arguments, 'remarks'),
                    $this->actor($arguments, $user),
                )),
                ['properties' => [
                    'stage' => $stage,
                    'remarks' => ['type' => 'string', 'description' => 'What has to change, markdown.'],
                    'agent' => $agent,
                ], 'required' => ['stage', 'remarks']],
                scope: self::REVIEW,
                permission: 'tasks.review',
            ),

            Tool::mutating(
                'stage_release',
                'Give back a stage that is in progress — its agent fell over or hung. It goes back to the queue '
                .'where it was taken from.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->onStage($arguments, fn (Stage $stage): Stage => $this->tasks->release(
                    $stage,
                    $this->actor($arguments, $user),
                    isset($arguments['reason']) ? (string) $arguments['reason'] : null,
                )),
                ['properties' => [
                    'stage' => $stage,
                    'reason' => ['type' => 'string'],
                    'agent' => $agent,
                ], 'required' => ['stage']],
                scope: self::WORK,
                permission: 'tasks.work',
            ),

            Tool::mutating(
                'stage_block',
                'Stop and ask a person — a decision, an access, a choice you cannot make. Ask one concrete '
                .'question, then end your session: do not wait for the answer. The stage is not handed out until a '
                .'person answers, and the answer goes to the session that takes it next.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->onStage($arguments, fn (Stage $stage): Stage => $this->tasks->block(
                    $stage,
                    $this->text($arguments, 'question'),
                    $this->actor($arguments, $user),
                )),
                ['properties' => [
                    'stage' => $stage,
                    'question' => ['type' => 'string'],
                    'agent' => $agent,
                ], 'required' => ['stage', 'question']],
                scope: self::WORK,
                permission: 'tasks.work',
            ),

            Tool::mutating(
                'stage_unblock',
                'Answer the question an agent asked on a blocked stage. Only for a person: the stage goes back to '
                .'the queue with the answer. An agent is refused — the question asked for a person\'s decision.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->onStage($arguments, fn (Stage $stage): Stage => $this->tasks->unblock(
                    $stage,
                    $this->text($arguments, 'answer'),
                    $this->person($user),
                )),
                ['properties' => [
                    'stage' => $stage,
                    'answer' => ['type' => 'string'],
                ], 'required' => ['stage', 'answer']],
                scope: self::WRITE,
                permission: 'tasks.manage',
            ),

            Tool::mutating(
                'comment',
                'Add a remark to the journal of a task, or of one of its stages.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->write($arguments, function () use ($arguments, $user): array {
                    $task = $this->task($arguments['task'] ?? null);
                    $stage = isset($arguments['stage']) ? $this->stage($arguments['stage']) : null;

                    $this->tasks->comment($task, $stage, $this->text($arguments, 'body'), $this->actor($arguments, $user));

                    return ['task' => Present::row($task->load('stages', 'project'))];
                }),
                ['properties' => [
                    'task' => $task,
                    'stage' => $stage + ['description' => 'A stage of that task, when the remark is about it.'],
                    'body' => ['type' => 'string', 'description' => 'Markdown.'],
                    'agent' => $agent,
                ], 'required' => ['task', 'body']],
                scope: [self::WRITE, self::WORK],
                permission: ['tasks.manage', 'tasks.work', 'tasks.review'],
            ),

            Tool::mutating(
                'cancel',
                'Cancel a task. Every stage not yet done is cancelled with it; nothing of it is handed out again.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->write($arguments, fn (): array => [
                    'task' => $this->full($this->tasks->cancel(
                        $this->task($arguments['task'] ?? null),
                        $this->actor($arguments, $user),
                        isset($arguments['reason']) ? (string) $arguments['reason'] : null,
                    )),
                ]),
                ['properties' => ['task' => $task, 'reason' => ['type' => 'string'], 'agent' => $agent], 'required' => ['task']],
                scope: self::WRITE,
                permission: 'tasks.manage',
            ),
        ], 'tasks_');
    }

    /**
     * Who may look: anybody who does any of the four kinds of work.
     *
     * @return list<string>
     */
    private function readers(): array
    {
        return ['tasks.view', 'tasks.manage', 'tasks.work', 'tasks.review'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $limit = min(200, max(1, (int) ($arguments['limit'] ?? 50)));
        $mode = isset($arguments['review_mode']) ? ReviewMode::tryFrom((string) $arguments['review_mode']) : null;

        $tasks = Task::query()
            ->with('project', 'stages')
            ->when(isset($arguments['project']), fn (Builder $query) => $query->where('project_id', $this->project($arguments['project'])->id))
            ->when(isset($arguments['status']), static fn (Builder $query) => $query->where('status', (string) $arguments['status']))
            ->when($mode !== null, static fn (Builder $query) => $query->whereHas('stages', static fn (Builder $stages) => $stages
                ->where('status', StageStatus::Review->value)
                ->where('review_mode', $mode?->value)))
            ->when(($arguments['waiting_for_person'] ?? false) === true, static fn (Builder $query) => $query->waitingForPerson())
            ->orderByRaw(
                'case priority when ? then 0 when ? then 1 else 2 end',
                [Priority::High->value, Priority::Normal->value],
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'count' => $tasks->count(),
            'tasks' => $tasks->map(Present::row(...))->values()->all(),
        ];
    }

    /**
     * Run a write, or rehearse it and roll it back: the dry run says what the write would say, after
     * every check the write has.
     *
     * @param  array<string, mixed>  $arguments
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function write(array $arguments, Closure $work): array
    {
        try {
            if (($arguments[Tool::DRY_RUN] ?? false) !== true) {
                return $work();
            }

            return ['dry_run' => true] + Rehearsal::run($work);
        } catch (TaskRefused $refusal) {
            throw new ToolFailure($refusal->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  Closure(Stage): Stage  $work
     * @return array<string, mixed>
     */
    private function onStage(array $arguments, Closure $work): array
    {
        return $this->write($arguments, function () use ($arguments, $work): array {
            $stage = $work($this->stage($arguments['stage'] ?? null));
            $task = $stage->task()->firstOrFail()->load('stages', 'project');

            return ['stage' => Present::stage($stage), 'task' => Present::row($task)];
        });
    }

    /**
     * The one who acts: the named agent, or the person behind the connection.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function actor(array $arguments, ?Authenticatable $user): Actor
    {
        $name = $arguments['agent'] ?? null;

        if (is_string($name) && trim($name) !== '') {
            return Actor::agent($name, $user);
        }

        return $this->person($user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(array $arguments, ?Authenticatable $user): Actor
    {
        $name = $arguments['agent'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw new ToolFailure('Name your session with `agent`: the stage you take is held under that name.');
        }

        return Actor::agent($name, $user);
    }

    /**
     * The administrator behind the connection, when that is a person: signed in to the panel, or
     * connected through the consent screen (a token carrying `mcp:use`). A key was made for a
     * program — one issued without scopes carries `mcp:use` too, which is why it is asked about
     * by what it is rather than by what it carries — and the local stdio server has nobody
     * behind it at all; neither speaks for a person.
     */
    private function person(?Authenticatable $user): Actor
    {
        if ($user === null) {
            throw new ToolFailure('Nobody is signed in on this connection. An agent names itself with `agent`; a person\'s decision needs a person\'s connection.');
        }

        $token = Scopes::tokenOf($user);
        $consented = method_exists($user, 'tokenCan') && $user->tokenCan(Scopes::OAUTH);

        if ($token !== null && (! $consented || Container::getInstance()->make(Keys::class)->isKey($user))) {
            throw new ToolFailure('This key was issued for a program, not a person. Name the agent with `agent`; a person\'s decision needs a person\'s connection.');
        }

        return Actor::admin($user);
    }

    private function project(mixed $reference): Project
    {
        $project = match (true) {
            is_int($reference) => Project::query()->find($reference),
            is_string($reference) && ctype_digit($reference) => Project::query()->find((int) $reference),
            is_string($reference) => Project::query()->where('slug', $reference)->first(),
            default => null,
        };

        if (! $project instanceof Project) {
            throw new ToolFailure('No such project. tasks_projects_list has them.');
        }

        return $project;
    }

    private function task(mixed $reference): Task
    {
        $task = null;

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $task = Task::query()->find((int) $reference);
        } elseif (is_string($reference) && preg_match('/^([a-z0-9][a-z0-9-]*)#(\d+)$/', $reference, $match) === 1) {
            $task = Task::query()
                ->whereHas('project', static fn (Builder $query) => $query->where('slug', $match[1]))
                ->where('number', (int) $match[2])
                ->first();
        }

        if (! $task instanceof Task) {
            throw new ToolFailure('No such task. Name it by id or as "<project>#<number>"; tasks_list has them.');
        }

        return $task;
    }

    private function stage(mixed $reference): Stage
    {
        $stage = is_int($reference) || (is_string($reference) && ctype_digit($reference))
            ? Stage::query()->whereHas('task')->find((int) $reference)
            : null;

        if (! $stage instanceof Stage) {
            throw new ToolFailure('No such stage. tasks_get and tasks_queue give the ids of stages.');
        }

        return $stage;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function text(array $arguments, string $name): string
    {
        $value = $arguments[$name] ?? null;

        if (! is_string($value)) {
            throw new ToolFailure("`{$name}` is required, as text.");
        }

        return $value;
    }

    /**
     * The arguments that are fields of the record, by the names the schema gives them.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function values(array $arguments, array $fields): array
    {
        return array_intersect_key($arguments, $fields);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stages(mixed $stages): array
    {
        if (! is_array($stages) || ! array_is_list($stages)) {
            throw new ToolFailure('`stages` must be a list of stages, first to last.');
        }

        foreach ($stages as $index => $stage) {
            if (! is_array($stage)) {
                throw new ToolFailure("Stage {$index} must be an object.");
            }

            Arguments::refuseUnknown($stage, ['id', 'title', 'brief', 'acceptance', 'review_mode'], "Stage {$index}");
        }

        /** @var list<array<string, mixed>> $stages */
        return $stages;
    }

    /**
     * @return array<string, mixed>
     */
    private function full(Task $task): array
    {
        return Present::task($task->load('stages', 'journal', 'project'));
    }
}
