<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Audit\Http\Controllers\IssueController;
use WebxUi\Audit\Http\Controllers\PageController;
use WebxUi\Audit\Http\Controllers\RunController;

Route::prefix((string) config('webx-admin.api_path').'/audit')
    ->middleware('webx.panel')
    ->name('webx.audit.')
    ->group(function (): void {
        Route::middleware('cms.can:audit.view,audit.run,audit.manage')->group(function (): void {
            Route::get('runs', [RunController::class, 'index'])->name('runs.index');
            Route::get('runs/latest', [RunController::class, 'latest'])->name('runs.latest');
            Route::get('runs/{run}', [RunController::class, 'show'])->whereNumber('run')->name('runs.show');
            Route::get('runs/{run}/checks', [IssueController::class, 'checks'])->whereNumber('run')->name('runs.checks');
            Route::get('runs/{run}/issues', [IssueController::class, 'index'])->whereNumber('run')->name('runs.issues');
            Route::get('runs/{run}/pages', [PageController::class, 'index'])->whereNumber('run')->name('runs.pages');
            Route::get('runs/{run}/pages/export', [PageController::class, 'export'])->whereNumber('run')->name('runs.pages.export');
            Route::get('runs/{run}/pages/{page}', [PageController::class, 'show'])->whereNumber(['run', 'page'])->name('runs.pages.show');
            Route::get('runs/{run}/pages/{page}/links', [PageController::class, 'links'])->whereNumber(['run', 'page'])->name('runs.pages.links');
            Route::get('runs/{run}/pages/{page}/resources', [PageController::class, 'resources'])->whereNumber(['run', 'page'])->name('runs.pages.resources');
        });

        Route::middleware('cms.can:audit.run,audit.manage')->group(function (): void {
            Route::post('runs', [RunController::class, 'store'])->name('runs.store');
            Route::post('runs/{run}/cancel', [RunController::class, 'cancel'])->whereNumber('run')->name('runs.cancel');
        });
    });
