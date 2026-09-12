<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Http\Controllers\ManifestController;
use WebxUi\Admin\Http\Controllers\ShellController;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware((array) config('webx-admin.api_middleware'))
    ->name('webx.api.')
    ->group(function (): void {
        Route::get('manifest', ManifestController::class)->name('manifest');
    });

Route::prefix((string) config('webx-admin.path'))
    ->middleware((array) config('webx-admin.middleware'))
    ->name('webx.')
    ->group(function (): void {
        // The panel owns every address below its prefix; a deep link must not 404 before the
        // front end has had a chance to route it.
        Route::get('{path?}', ShellController::class)
            ->where('path', '.*')
            ->name('shell');
    });
