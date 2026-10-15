<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Tasks\Http\Controllers\ProjectController;
use WebxUi\Tasks\Http\Controllers\SettingsController;
use WebxUi\Tasks\Http\Controllers\StageController;
use WebxUi\Tasks\Http\Controllers\TaskController;

/*
 * The panel's door to the queue (§6). The same operations the MCP tools call, behind the same four
 * permissions: anybody who does any of the work may look; planning is `tasks.manage`; a decision
 * on a reported stage is `tasks.review`; answering an agent's question is `tasks.manage`. Whoever
 * acts here is a person — the panel is a person's connection.
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.tasks.panel.')
    ->group(function (): void {
        Route::middleware('cms.can:tasks.view,tasks.manage,tasks.work,tasks.review')->group(function (): void {
            Route::get('tasks/projects', [ProjectController::class, 'index'])->name('projects.index');
            Route::get('tasks/tasks', [TaskController::class, 'index'])->name('tasks.index');
            Route::get('tasks/tasks/{task}', [TaskController::class, 'show'])->whereNumber('task')->name('tasks.show');
        });

        Route::middleware('cms.can:tasks.manage')->group(function (): void {
            Route::post('tasks/projects', [ProjectController::class, 'store'])->name('projects.store');
            Route::put('tasks/projects/{project}', [ProjectController::class, 'update'])->whereNumber('project')->name('projects.update');

            Route::post('tasks/tasks', [TaskController::class, 'store'])->name('tasks.store');
            Route::put('tasks/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task')->name('tasks.update');
            Route::post('tasks/tasks/{task}/ready', [TaskController::class, 'ready'])->whereNumber('task')->name('tasks.ready');
            Route::post('tasks/tasks/{task}/cancel', [TaskController::class, 'cancel'])->whereNumber('task')->name('tasks.cancel');

            Route::post('tasks/stages/{stage}/unblock', [StageController::class, 'unblock'])->whereNumber('stage')->name('stages.unblock');
            // Notifications (§6a): the chats and the token are the planner's to set.
            Route::get('tasks/settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('tasks/settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('tasks/settings/test', [SettingsController::class, 'test'])->name('settings.test');

            Route::post('tasks/stages/{stage}/cancel', [StageController::class, 'cancel'])->whereNumber('stage')->name('stages.cancel');
        });

        Route::middleware('cms.can:tasks.manage,tasks.work')->group(function (): void {
            Route::post('tasks/stages/{stage}/release', [StageController::class, 'release'])->whereNumber('stage')->name('stages.release');
        });

        Route::middleware('cms.can:tasks.manage,tasks.work,tasks.review')->group(function (): void {
            Route::post('tasks/tasks/{task}/comments', [TaskController::class, 'comment'])->whereNumber('task')->name('tasks.comment');
        });

        Route::middleware('cms.can:tasks.review')->group(function (): void {
            Route::post('tasks/stages/{stage}/accept', [StageController::class, 'accept'])->whereNumber('stage')->name('stages.accept');
            Route::post('tasks/stages/{stage}/rework', [StageController::class, 'rework'])->whereNumber('stage')->name('stages.rework');
        });
    });
