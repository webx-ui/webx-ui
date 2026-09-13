<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Media\Http\Controllers\DirectoryController;

Route::prefix((string) config('webx-admin.api_path').'/media')
    // `web` for the session the panel signs in with, the locale middleware so an error is worded
    // in the language the browser is reading, and `cms.auth` because a library is not public.
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.media.')
    ->group(function (): void {
        Route::middleware('cms.can:media.view,media.manage')->group(function (): void {
            Route::get('directories', [DirectoryController::class, 'index'])->name('directories.index');
        });

        Route::middleware('cms.can:media.manage')->group(function (): void {
            Route::post('directories', [DirectoryController::class, 'store'])->name('directories.store');
            Route::patch('directories/{directory}', [DirectoryController::class, 'update'])->name('directories.update');
            Route::patch('directories/{directory}/move', [DirectoryController::class, 'move'])->name('directories.move');
            Route::delete('directories/{directory}', [DirectoryController::class, 'destroy'])->name('directories.destroy');
        });
    });
