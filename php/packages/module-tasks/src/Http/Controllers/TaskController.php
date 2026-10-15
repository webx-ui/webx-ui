<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\Workflow\Actor;
use WebxUi\Tasks\Workflow\Present;
use WebxUi\Tasks\Workflow\Priority;
use WebxUi\Tasks\Workflow\Tasks;
use WebxUi\Tasks\Workflow\TaskStatus;

/**
 * Tasks in the panel (§6, screens 1–3): the list with its views, one task, and the writes the
 * task screen and the form make.
 */
final class TaskController
{
    use Refusals;

    /** The task fields the form sends; everything else in the body is not the task's. */
    private const array FIELDS = ['title', 'priority', 'spec', 'acceptance', 'branch'];

    /** The list's views (§6.1): what each tab narrows to. */
    private const array VIEWS = ['all', 'waiting', 'active', 'draft', 'closed'];

    public function __construct(private readonly Tasks $tasks) {}

    /**
     * A page of tasks in Laravel's own paginator shape, with the count of every view for the tabs
     * — highest priority first, then the most recently changed.
     */
    public function index(Request $request): JsonResponse
    {
        $view = in_array($request->query('view'), self::VIEWS, true) ? (string) $request->query('view') : 'all';
        $base = $this->filtered($request);

        $page = $this->view(clone $base, $view)
            ->with('project', 'stages')
            ->orderByRaw('case priority when ? then 0 when ? then 1 else 2 end', [Priority::High->value, Priority::Normal->value])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->query('per_page', '20'))))
            ->through(static fn (Task $task): array => Present::row($task));

        $counts = [];

        foreach (self::VIEWS as $one) {
            $counts[$one] = $this->view(clone $base, $one)->count();
        }

        return new JsonResponse(['counts' => $counts, ...$page->toArray()]);
    }

    public function show(int $task): JsonResponse
    {
        return $this->answer(Task::query()->findOrFail($task));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->refusing(function () use ($request): JsonResponse {
            $task = $this->tasks->create(
                Project::query()->findOrFail((int) $request->input('project_id')),
                $request->only(self::FIELDS),
                $this->stages($request),
                Actor::admin($this->user($request)),
            );

            return $this->answer($task)->setStatusCode(201);
        });
    }

    public function update(Request $request, int $task): JsonResponse
    {
        return $this->refusing(fn (): JsonResponse => $this->answer($this->tasks->update(
            Task::query()->findOrFail($task),
            $request->only(self::FIELDS),
            $request->has('stages') ? $this->stages($request) : null,
            Actor::admin($this->user($request)),
        )));
    }

    public function ready(Request $request, int $task): JsonResponse
    {
        return $this->refusing(fn (): JsonResponse => $this->answer(
            $this->tasks->ready(Task::query()->findOrFail($task), Actor::admin($this->user($request))),
        ));
    }

    public function cancel(Request $request, int $task): JsonResponse
    {
        return $this->refusing(fn (): JsonResponse => $this->answer($this->tasks->cancel(
            Task::query()->findOrFail($task),
            Actor::admin($this->user($request)),
            $request->string('reason')->toString(),
        )));
    }

    public function comment(Request $request, int $task): JsonResponse
    {
        return $this->refusing(function () use ($request, $task): JsonResponse {
            $model = Task::query()->findOrFail($task);
            $stage = $request->filled('stage_id') ? Stage::query()->findOrFail((int) $request->input('stage_id')) : null;

            $this->tasks->comment($model, $stage, $request->string('body')->toString(), Actor::admin($this->user($request)));

            return $this->answer($model);
        });
    }

    /**
     * @return Builder<Task>
     */
    private function filtered(Request $request): Builder
    {
        $search = trim($request->string('q')->toString());

        return Task::query()
            ->when($request->filled('project'), static fn (Builder $query) => $query->where('project_id', (int) $request->query('project')))
            ->when($search !== '', static fn (Builder $query) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'));
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    private function view(Builder $query, string $view): Builder
    {
        return match ($view) {
            'waiting' => $query->waitingForPerson(),
            'active' => $query->whereIn('status', [TaskStatus::Ready->value, TaskStatus::InProgress->value, TaskStatus::Review->value]),
            'draft' => $query->where('status', TaskStatus::Draft->value),
            'closed' => $query->whereIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value]),
            default => $query,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stages(Request $request): array
    {
        $stages = $request->input('stages', []);

        /** @var list<array<string, mixed>> */
        return is_array($stages) ? array_values(array_filter($stages, is_array(...))) : [];
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();

        abort_if($user === null, 401);

        return $user;
    }
}
