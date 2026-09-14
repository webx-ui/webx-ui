<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Settings\Http\Controllers\SettingsController;

Route::prefix((string) config('webx-admin.api_path').'/settings')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.settings.')
    ->group(function (): void {
        Route::get('', [SettingsController::class, 'index'])
            ->middleware('cms.can:settings.view,settings.manage')
            ->name('index');

        Route::put('', [SettingsController::class, 'update'])
            ->middleware('cms.can:settings.manage')
            ->name('update');
    });
