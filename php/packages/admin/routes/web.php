<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Http\Controllers\LocaleController;
use WebxUi\Admin\Http\Controllers\ManifestController;
use WebxUi\Admin\Http\Controllers\ShellController;
use WebxUi\Admin\Http\Controllers\TranslationController;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware((array) config('webx-admin.api_middleware'))
    ->name('webx.api.')
    ->group(function (): void {
        Route::get('manifest', ManifestController::class)->name('manifest');
    });

// The two things the panel needs before it can draw the sign-in screen, and therefore before
// there is anybody to authenticate. Deliberately outside the group above, which installing
// webx-ui/module-auth closes.
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale'])
    ->name('webx.api.')
    ->group(function (): void {
        Route::get('locales', LocaleController::class)->name('locales');
        Route::get('translations/{locale}', TranslationController::class)
            ->where('locale', '[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?')
            ->name('translations');
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
