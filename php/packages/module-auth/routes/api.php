<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Auth\Http\Controllers\MeController;
use WebxUi\Auth\Http\Controllers\PanelLocaleController;
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
            Route::put('locale', PanelLocaleController::class)->name('locale');
        });
    });
