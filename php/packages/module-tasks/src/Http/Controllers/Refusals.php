<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Tasks\Exceptions\TaskRefused;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\Workflow\Present;

/**
 * A move the rules of §3 do not allow is a 422 with the reason in words — and the key, so the
 * panel can tell refusals apart without reading the text.
 *
 * Not a 409, though "somebody got there first" is often why: the panel reads every 409 as an edit
 * made meanwhile and says so in its own words, and the reason written here never reached anyone.
 */
trait Refusals
{
    /**
     * @param  Closure(): JsonResponse  $work
     */
    private function refusing(Closure $work): JsonResponse
    {
        try {
            return $work();
        } catch (TaskRefused $refusal) {
            return new JsonResponse(['message' => $refusal->getMessage(), 'reason' => $refusal->reason], 422);
        }
    }

    /** What the panel answers with after a change: the task as the screen shows it. */
    private function answer(Task $task): JsonResponse
    {
        return ApiResponse::data(Present::task($task->refresh()->load('stages', 'journal', 'project')));
    }
}
