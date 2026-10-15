<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Workflow\Present;
use WebxUi\Tasks\Workflow\Tasks;

/**
 * The projects: a short list, every one of them, archived ones last — there are a handful, and a
 * list of a handful does not page.
 */
final class ProjectController
{
    private const array FIELDS = ['slug', 'title', 'workdir', 'repo_url', 'default_branch', 'rules', 'archived'];

    public function __construct(private readonly Tasks $tasks) {}

    public function index(): JsonResponse
    {
        $projects = Project::query()
            ->withCount('tasks')
            ->orderByRaw('archived_at is not null')
            ->orderBy('title')
            ->get();

        return ApiResponse::data($projects->map(static fn (Project $project): array => [
            ...Present::project($project),
            'tasks_count' => (int) $project->getAttribute('tasks_count'),
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $project = $this->tasks->createProject($request->only(self::FIELDS));

        return ApiResponse::data(Present::project($project), 201);
    }

    public function update(Request $request, int $project): JsonResponse
    {
        $saved = $this->tasks->updateProject(Project::query()->findOrFail($project), $request->only(self::FIELDS));

        return ApiResponse::data(Present::project($saved));
    }
}
