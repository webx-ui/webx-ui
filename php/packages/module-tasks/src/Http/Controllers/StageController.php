<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Http\Controllers;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Workflow\Actor;
use WebxUi\Tasks\Workflow\Tasks;

/**
 * The buttons of a stage on the task screen (§6.2): accept, send back, answer, release, cancel.
 * Each answers the whole task, because one move changes the stage after it and the task's status
 * too.
 */
final class StageController
{
    use Refusals;

    public function __construct(private readonly Tasks $tasks) {}

    public function accept(Request $request, int $stage): JsonResponse
    {
        return $this->on($stage, fn (Stage $model, Actor $actor) => $this->tasks->accept($model, $actor, $request->string('note')->toString()), $request);
    }

    public function rework(Request $request, int $stage): JsonResponse
    {
        return $this->on($stage, fn (Stage $model, Actor $actor) => $this->tasks->rework($model, $request->string('remarks')->toString(), $actor), $request);
    }

    public function unblock(Request $request, int $stage): JsonResponse
    {
        return $this->on($stage, fn (Stage $model, Actor $actor) => $this->tasks->unblock($model, $request->string('answer')->toString(), $actor), $request);
    }

    public function release(Request $request, int $stage): JsonResponse
    {
        return $this->on($stage, fn (Stage $model, Actor $actor) => $this->tasks->release($model, $actor, $request->string('reason')->toString()), $request);
    }

    public function cancel(Request $request, int $stage): JsonResponse
    {
        return $this->on($stage, fn (Stage $model, Actor $actor) => $this->tasks->cancelStage($model, $actor, $request->string('reason')->toString()), $request);
    }

    /**
     * @param  Closure(Stage, Actor): Stage  $work
     */
    private function on(int $stage, Closure $work, Request $request): JsonResponse
    {
        $model = Stage::query()->whereHas('task')->findOrFail($stage);
        $user = $request->user();

        abort_unless($user instanceof Authenticatable, 401);

        return $this->refusing(function () use ($model, $work, $user): JsonResponse {
            $work($model, Actor::admin($user));

            return $this->answer($model->task()->firstOrFail());
        });
    }
}
