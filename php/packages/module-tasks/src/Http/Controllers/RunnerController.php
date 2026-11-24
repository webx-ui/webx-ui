<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Tasks\Runner\Heartbeats;
use WebxUi\Tasks\Runner\RunnerSettings;

/**
 * The screen «Agents» (§14.3): every watcher's last heartbeat, whether it has gone silent, and the
 * settings it is told — the same answer as `tasks_runner_status`.
 */
final class RunnerController
{
    public function show(RunnerSettings $settings, Heartbeats $heartbeats): JsonResponse
    {
        return ApiResponse::data([
            'config' => $settings->all(),
            'silent_after_minutes' => $settings->silentAfter(),
            'runners' => $heartbeats->status(),
        ]);
    }
}
