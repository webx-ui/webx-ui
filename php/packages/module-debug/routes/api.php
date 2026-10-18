<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Debug\Http\Controllers\ReportController;
use WebxUi\Debug\Panel\DebugModule;

/*
 * The reports, each only to the administrator who made it (§9). Not registered at all in
 * production: there, the addresses are a 404 like any other.
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['webx.panel', 'cms.can:'.DebugModule::PERMISSION])
    ->name('webx.debug.')
    ->group(function (): void {
        Route::get('debug/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('debug/reports/{id}', [ReportController::class, 'show'])
            ->where('id', '[0-9A-HJKMNP-TV-Z]{26}')
            ->name('reports.show');
    });
