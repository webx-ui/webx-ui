<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Inbox\Http\Controllers\SubmissionFileController;

/**
 * The panel's side of the module.
 *
 * Only the one route for now: everything a visitor attached is read through here and nowhere
 * else (§8). The rest of §12 — forms, fields, statuses, submissions — arrives with the screens
 * that ask for it.
 */
Route::prefix((string) config('webx-admin.api_path').'/inbox')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.inbox.')
    ->group(function (): void {
        Route::middleware('cms.can:inbox.view')->group(function (): void {
            Route::get('submissions/{submission}/files/{file}', SubmissionFileController::class)
                ->whereNumber('submission')
                ->whereNumber('file')
                ->name('files.show');
        });
    });
