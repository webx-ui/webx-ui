<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Pages\Http\Controllers\PageController;
use WebxUi\Pages\Http\Controllers\PageDuplicateController;
use WebxUi\Pages\Http\Controllers\PageMoveController;
use WebxUi\Pages\Http\Controllers\PagePublicationController;
use WebxUi\Pages\Http\Controllers\PageRestoreController;

Route::prefix((string) config('webx-admin.api_path').'/pages')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.pages.')
    ->group(function (): void {
        Route::middleware('cms.can:pages.view,pages.manage')->group(function (): void {
            Route::get('/', [PageController::class, 'index'])->name('index');
            Route::get('{page}', [PageController::class, 'show'])->whereNumber('page')->name('show');
        });

        Route::middleware('cms.can:pages.manage')->group(function (): void {
            Route::post('/', [PageController::class, 'store'])->name('store');
            Route::put('{page}', [PageController::class, 'update'])->whereNumber('page')->name('update');
            Route::delete('{page}', [PageController::class, 'destroy'])->whereNumber('page')->name('destroy');

            Route::post('{page}/move', PageMoveController::class)->whereNumber('page')->name('move');
            Route::post('{page}/duplicate', PageDuplicateController::class)->whereNumber('page')->name('duplicate');
            Route::post('{page}/publish', [PagePublicationController::class, 'publish'])->whereNumber('page')->name('publish');
            Route::post('{page}/unpublish', [PagePublicationController::class, 'unpublish'])->whereNumber('page')->name('unpublish');

            // Not model-bound: the page this one is about is in the bin, and the binding of
            // every other route here cannot see it.
            Route::post('{page}/restore', PageRestoreController::class)->whereNumber('page')->name('restore');
        });
    });
