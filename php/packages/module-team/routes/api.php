<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Team\Http\Controllers\MemberController;
use WebxUi\Team\Http\Controllers\MemberRestoreController;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.team.panel.')
    ->group(function (): void {
        Route::middleware('cms.can:team.view,team.manage')->group(function (): void {
            Route::get('team', [MemberController::class, 'index'])->name('team.index');
            Route::get('team/{member}', [MemberController::class, 'show'])->whereNumber('member')->name('team.show');
        });

        Route::middleware('cms.can:team.manage')->group(function (): void {
            Route::post('team', [MemberController::class, 'store'])->name('team.store');
            Route::post('team/reorder', [MemberController::class, 'reorder'])->name('team.reorder');
            Route::put('team/{member}', [MemberController::class, 'update'])->whereNumber('member')->name('team.update');
            Route::delete('team/{member}', [MemberController::class, 'destroy'])->whereNumber('member')->name('team.destroy');

            // Not model-bound: the person this one is about is in the bin, where the binding of
            // every other route here cannot see them.
            Route::post('team/{member}/restore', MemberRestoreController::class)
                ->whereNumber('member')
                ->name('team.restore');
        });
    });
