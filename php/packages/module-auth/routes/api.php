<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Auth\Http\Controllers\AdminController;
use WebxUi\Auth\Http\Controllers\MeController;
use WebxUi\Auth\Http\Controllers\PanelLocaleController;
use WebxUi\Auth\Http\Controllers\PanelThemeController;
use WebxUi\Auth\Http\Controllers\ProfileController;
use WebxUi\Auth\Http\Controllers\RoleController;
use WebxUi\Auth\Http\Controllers\SessionController;

Route::prefix((string) config('webx-admin.api_path').'/auth')
    // The language before the session: a sign-in that fails has to say so in a language the
    // person reading it understands, and at that point there is nobody to ask but the browser.
    ->middleware(['web', 'webx.panel-locale'])
    ->name('webx.auth.')
    ->group(function (): void {
        // Public by necessity, throttled because of it.
        Route::post('login', [SessionController::class, 'store'])
            ->middleware('throttle:'.(string) config('webx-auth.throttle'))
            ->name('login');

        Route::middleware('cms.auth')->group(function (): void {
            Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
            Route::get('me', MeController::class)->name('me');
            // Yourself, and only the parts of yourself that are yours to change. Deliberately
            // not behind 'admins.manage': that permission is about other people.
            Route::put('me', ProfileController::class)->name('me.update');
            Route::put('locale', PanelLocaleController::class)->name('locale');
            Route::put('theme', PanelThemeController::class)->name('theme');

            // Reading the list is not managing it: a module that wants to show who wrote
            // something, or offer a picker of people to assign work to, needs the first and
            // has no business with the second.
            Route::middleware('cms.can:admins.view,admins.manage')->group(function (): void {
                Route::get('roles', RoleController::class)->name('roles');
                Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
                Route::get('admins/{admin}', [AdminController::class, 'show'])->name('admins.show');
            });

            Route::middleware('cms.can:admins.manage')->group(function (): void {
                Route::post('admins', [AdminController::class, 'store'])->name('admins.store');
                Route::patch('admins/{admin}', [AdminController::class, 'update'])->name('admins.update');
                Route::delete('admins/{admin}', [AdminController::class, 'destroy'])->name('admins.destroy');
            });
        });
    });
