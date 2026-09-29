<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Press\Http\Controllers\OutletController;
use WebxUi\Press\Http\Controllers\OutletRestoreController;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.press.panel.')
    ->group(function (): void {
        Route::middleware('cms.can:press.view,press.manage')->group(function (): void {
            Route::get('press', [OutletController::class, 'index'])->name('outlets.index');
            Route::get('press/{outlet}', [OutletController::class, 'show'])->whereNumber('outlet')->name('outlets.show');
        });

        Route::middleware('cms.can:press.manage')->group(function (): void {
            // `reorder` is a word, and the numbers-only binding below is what keeps the two from
            // reading each other's addresses.
            Route::post('press/reorder', [OutletController::class, 'reorder'])->name('outlets.reorder');

            Route::post('press', [OutletController::class, 'store'])->name('outlets.store');
            Route::put('press/{outlet}', [OutletController::class, 'update'])->whereNumber('outlet')->name('outlets.update');
            Route::delete('press/{outlet}', [OutletController::class, 'destroy'])->whereNumber('outlet')->name('outlets.destroy');

            // Not model-bound: the outlet this one is about is in the bin, where the binding of
            // every other route here cannot see it.
            Route::post('press/{outlet}/restore', OutletRestoreController::class)
                ->whereNumber('outlet')
                ->name('outlets.restore');
        });
    });
